<?php

use App\Models\ApplicationUrl;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('platform administrator manages companies with dynamic system and member counts', function () {
    $admin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create(['name' => 'Alpha']);
    [$application] = simpleApplication($tenant);
    User::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->get(route('tenants.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/ResourceIndex')
        ->where('items.data.0.applications_text', $application->name)
        ->where('items.data.0.users_count', 2));
});

test('company system removal clears affected role menus', function () {
    $admin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create();
    [$application, , $menu] = simpleApplication($tenant);
    $role = Role::factory()->create(['tenant_id' => $tenant->id]);
    $role->menus()->attach($menu);

    $this->actingAs($admin)->put(route('tenants.update', $tenant), [
        'name' => $tenant->name, 'application_ids' => [], 'status' => true,
    ])->assertRedirect();

    expect($tenant->applications()->whereKey($application)->exists())->toBeFalse()
        ->and($role->menus()->exists())->toBeFalse();
});

test('company administrator creates only users in own company and cannot promote platform admins', function () {
    [$admin, $tenant, $role] = simpleCompanyUser(admin: true);
    $otherRole = Role::factory()->create();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Member', 'account' => 'Member_01', 'password' => 'password123',
        'tenant_id' => $otherRole->tenant_id, 'role_id' => $role->id,
        'is_company_admin' => false, 'is_platform_admin' => true, 'status' => true,
    ])->assertRedirect();

    $member = User::query()->where('account', 'Member_01')->firstOrFail();
    expect($member->tenant_id)->toBe($tenant->id)
        ->and($member->role_id)->toBe($role->id)
        ->and($member->is_platform_admin)->toBeFalse();
});

test('administrator can reset a user password', function () {
    $admin = User::factory()->platformAdmin()->create();
    [$user, $tenant, $role] = simpleCompanyUser();

    $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => $user->name, 'account' => $user->account, 'password' => 'new-password',
        'tenant_id' => $tenant->id, 'role_id' => $role->id,
        'is_company_admin' => false, 'is_platform_admin' => false, 'status' => true,
    ])->assertRedirect();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('role accepts only menus from systems opened for its company', function () {
    $admin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create();
    [, , $allowedMenu] = simpleApplication($tenant);
    $foreignMenu = Menu::factory()->create();

    $this->actingAs($admin)->post(route('roles.store'), [
        'tenant_id' => $tenant->id, 'name' => 'Operator',
        'menu_ids' => [$allowedMenu->id, $foreignMenu->id], 'status' => true,
    ])->assertRedirect();

    expect(Role::query()->where('name', 'Operator')->firstOrFail()->menus()->pluck('auc_menus.id')->all())
        ->toBe([$allowedMenu->id]);
});

test('menu hierarchy is limited to two levels within one system', function () {
    $admin = User::factory()->platformAdmin()->create();
    [$application] = simpleApplication(Tenant::factory()->create());
    $parent = Menu::factory()->create(['application_id' => $application->id, 'parent_id' => null]);
    $child = Menu::factory()->create(['application_id' => $application->id, 'parent_id' => $parent->id]);

    $this->actingAs($admin)->post(route('menus.store'), [
        'application_id' => $application->id, 'parent_id' => $child->id,
        'name' => 'Third', 'path' => '/third', 'is_visible' => true,
        'sort_order' => 1, 'status' => true,
    ])->assertUnprocessable();
});

test('new menu hides status field and defaults to enabled', function () {
    $admin = User::factory()->platformAdmin()->create();
    [$application] = simpleApplication(Tenant::factory()->create());

    $this->actingAs($admin)
        ->get(route('menus.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.fields.6.name', 'status')
            ->where('resource.fields.6.updateOnly', true));

    $this->post(route('menus.store'), [
        'application_id' => $application->id,
        'parent_id' => null,
        'name' => 'Reports',
        'path' => '/reports',
        'is_visible' => true,
        'sort_order' => 1,
    ])->assertRedirect();

    expect(Menu::query()->where('path', '/reports')->firstOrFail()->status)->toBeTrue();
});

test('system stores multiple exact sso callback urls', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)->post(route('applications.store'), [
        'name' => 'Global System',
        'base_url' => 'https://one.example.test',
        'redirect_uri' => 'https://one.example.test/sso/callback',
        'additional_urls' => "https://two.example.test | https://two.example.test/sso/callback\nhttps://three.example.test | https://three.example.test/sso/callback",
        'status' => true,
    ])->assertRedirect();

    expect(ApplicationUrl::query()->count())->toBe(3)
        ->and(ApplicationUrl::query()->where('is_default', true)->count())->toBe(1);
});
