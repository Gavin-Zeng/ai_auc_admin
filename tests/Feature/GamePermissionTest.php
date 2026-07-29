<?php

use App\Models\Game;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGamePermission;
use App\Support\PermissionResolver;
use Inertia\Testing\AssertableInertia as Assert;

test('user game scopes include mother and child grants', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $first = Game::query()->create(['name' => '子游戏 A', 'old_name' => '母游戏', 'app_id' => 'child_a', 'old_id' => 'mother_1', 'game' => 'child_a', 'status' => true]);
    $second = Game::query()->create(['name' => '子游戏 B', 'old_name' => '母游戏', 'app_id' => 'child_b', 'old_id' => 'mother_1', 'game' => 'child_b', 'status' => true]);
    UserGamePermission::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'scope_type' => 'MOTHER', 'scope_key' => 'mother_1']);

    $scopes = app(PermissionResolver::class)->resolve($user, $tenant)['business_scopes'];
    expect($scopes)->toHaveCount(2)
        ->and(collect($scopes)->pluck('game_id')->sort()->values()->all())->toBe(['child_a', 'child_b']);
    expect($first->exists)->toBeTrue()->and($second->exists)->toBeTrue();
});

test('only platform administrator can view game catalog', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    [$companyAdmin] = simpleCompanyUser(admin: true);
    Game::query()->create([
        'name' => '子游戏',
        'old_name' => '母游戏',
        'app_id' => 'child',
        'old_id' => 'mother',
        'pkg_name' => 'ai.bingniao.game',
        'status' => true,
    ]);

    $this->actingAs($admin)->get(route('games.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/ResourceIndex')
            ->where('resource.columns', ['name', 'app_id', 'old_name', 'old_id', 'pkg_name'])
            ->where('items.data.0.name', '子游戏')
            ->where('items.data.0.app_id', 'child')
            ->where('items.data.0.old_name', '母游戏')
            ->where('items.data.0.old_id', 'mother')
            ->where('items.data.0.pkg_name', 'ai.bingniao.game'));
    $this->actingAs($companyAdmin)->get(route('games.index'))->assertForbidden();
});

test('child permission resolves only selected app id', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Game::query()->create(['name' => '子游戏 A', 'old_name' => '母游戏', 'app_id' => 'child_a', 'old_id' => 'mother_1', 'status' => true]);
    Game::query()->create(['name' => '子游戏 B', 'old_name' => '母游戏', 'app_id' => 'child_b', 'old_id' => 'mother_1', 'status' => true]);
    UserGamePermission::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'scope_type' => 'CHILD', 'scope_key' => 'child_b']);

    expect(app(PermissionResolver::class)->resolve($user, $tenant)['business_scopes'])->toHaveCount(1)
        ->and(app(PermissionResolver::class)->resolve($user, $tenant)['business_scopes'][0]['game_id'])->toBe('child_b');
});

test('user management opens game permissions for the selected user', function (): void {
    [$admin, $tenant] = simpleCompanyUser(admin: true);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'name' => '测试用户']);
    Game::query()->create(['name' => '子游戏', 'old_name' => '母游戏', 'app_id' => 'child_1', 'old_id' => 'mother_1', 'status' => true]);
    UserGamePermission::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'scope_type' => 'MOTHER', 'scope_key' => 'mother_1']);

    $this->actingAs($admin)->get(route('users.game-permissions.index', ['user' => $user->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/GamePermissions')
            ->where('user.id', $user->id)
            ->where('user.account', $user->account)
            ->where('permissions.0.scope_type', 'MOTHER')
            ->where('permissions.0.scope_key', 'mother_1'));
});

test('legacy game permission entry redirects to user management', function (): void {
    [$admin] = simpleCompanyUser(admin: true);

    $this->actingAs($admin)->get(route('game-permissions.index'))
        ->assertRedirect(route('users.index'));
});

test('user game permission update stores selected scopes', function (): void {
    [$admin, $tenant] = simpleCompanyUser(admin: true);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Game::query()->create(['name' => '子游戏', 'old_name' => '母游戏', 'app_id' => 'child_1', 'old_id' => 'mother_1', 'status' => true]);

    $this->actingAs($admin)->put(route('users.game-permissions.update', ['user' => $user->id]), [
        'tenant_id' => $tenant->id,
        'permissions' => [
            ['scope_type' => 'MOTHER', 'scope_key' => 'mother_1'],
            ['scope_type' => 'CHILD', 'scope_key' => 'child_1'],
        ],
    ])->assertRedirect();

    expect($user->gamePermissions()->pluck('scope_key')->sort()->values()->all())
        ->toBe(['child_1', 'mother_1']);
});
