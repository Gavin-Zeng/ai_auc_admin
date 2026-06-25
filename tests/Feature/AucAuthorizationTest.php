<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

test('role permission changes take effect immediately', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
        'permission_version' => 1,
    ]);

    $role = Role::factory()->create(['tenant_id' => $tenant->id]);
    $user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get('/applications')
        ->assertForbidden();

    $permission = Permission::factory()->create(['code' => 'applications.manage']);
    $role->permissions()->attach($permission->id);

    $this->get('/applications')->assertOk();
});

test('regular users cannot switch into another tenant', function () {
    $allowedTenant = Tenant::factory()->create();
    $blockedTenant = Tenant::factory()->create();
    $user = User::factory()->create();

    TenantUser::query()->create([
        'tenant_id' => $allowedTenant->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('tenant.switch'), ['tenant_id' => $blockedTenant->id])
        ->assertForbidden();
});

test('platform admins can switch across tenants', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->post(route('tenant.switch'), ['tenant_id' => $tenant->id])
        ->assertRedirect();
});

test('company owners can manage their company but not platform company records', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    aucGrant($owner, $tenant, [], isOwner: true);

    $this->actingAs($owner)
        ->get(route('applications.index'))
        ->assertOk();

    $this->get(route('tenants.index'))->assertForbidden();
    $this->get(route('diagnostics.index'))->assertForbidden();
});

test('tenant scoped navigation does not leak menus across tenants', function () {
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $firstTenant, ['dashboard.view']);

    Menu::factory()->create([
        'tenant_id' => $firstTenant->id,
        'title' => 'Visible Menu',
        'required_permissions' => ['dashboard.view'],
    ]);
    Menu::factory()->create([
        'tenant_id' => $secondTenant->id,
        'title' => 'Leaked Menu',
        'required_permissions' => [],
    ]);

    $this->actingAs($user)
        ->getJson(route('api.navigation'))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Visible Menu'])
        ->assertJsonMissing(['title' => 'Leaked Menu']);
});

test('hidden menu does not authorize the protected endpoint', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    Menu::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Hidden Applications',
        'href' => '/applications',
        'required_permissions' => ['applications.manage'],
        'is_visible' => false,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.navigation'))
        ->assertOk()
        ->assertJsonMissing(['title' => 'Hidden Applications']);

    $this->get('/applications')->assertForbidden();
});
