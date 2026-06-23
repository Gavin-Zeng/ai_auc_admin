<?php

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('platform admin can create and disable a tenant', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->post(route('tenants.store'), [
            'code' => 'global',
            'name' => 'Global Tenant',
            'domain' => 'global.example.test',
            'status' => 'active',
        ])
        ->assertRedirect();

    $tenant = Tenant::query()->where('code', 'global')->firstOrFail();
    expect($tenant->status)->toBe('active');

    $this->delete(route('tenants.destroy', $tenant))->assertRedirect();
    expect($tenant->refresh()->status)->toBe('disabled');
});

test('tenant admin can manage roles and permission version changes', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['roles.manage']);

    $permission = Permission::factory()->create(['code' => 'reports.view']);
    $before = TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $admin->id)
        ->value('permission_version');

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'code' => 'analyst',
            'name' => 'Analyst',
            'status' => 'active',
            'permission_ids' => [$permission->id],
        ])
        ->assertRedirect();

    $role = Role::query()->where('tenant_id', $tenant->id)->where('code', 'analyst')->firstOrFail();

    expect($role->permissions()->pluck('auc_permissions.code')->all())->toBe(['reports.view']);
    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $admin->id)
        ->value('permission_version'))->toBeGreaterThan($before);
});

test('application secret can be rotated and audited', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['applications.manage']);

    $application = Application::factory()->create([
        'tenant_id' => $tenant->id,
        'client_secret' => 'old-secret',
    ]);

    $this->actingAs($admin)
        ->post(route('applications.rotate-secret', $application))
        ->assertRedirect()
        ->assertSessionHas('secret');

    expect(Hash::check('old-secret', $application->refresh()->client_secret))->toBeFalse();
    expect(AuditLog::query()->where('action', 'application.secret_rotated')->exists())->toBeTrue();
});

test('application changes bump permission version and hide client secret from admin props', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['applications.manage']);

    $application = Application::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => 'billing-client',
        'client_secret' => 'visible-once',
        'required_permissions' => [],
    ]);
    $before = TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $admin->id)
        ->value('permission_version');

    $this->actingAs($admin)
        ->put(route('applications.update', $application), [
            'code' => $application->code,
            'name' => $application->name,
            'client_id' => $application->client_id,
            'base_url' => $application->base_url,
            'redirect_uri' => $application->redirect_uri,
            'status' => 'active',
            'required_permissions' => ['applications.manage'],
        ])
        ->assertRedirect();

    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $admin->id)
        ->value('permission_version'))->toBeGreaterThan($before);

    $this->get(route('applications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.data.0.client_id', 'billing-client')
            ->missing('items.data.0.client_secret'));
});

test('permission snapshot exposes versioned local authorization data', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    Menu::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Dashboard',
        'required_permissions' => ['dashboard.view'],
    ]);
    Application::factory()->create([
        'tenant_id' => $tenant->id,
        'required_permissions' => ['dashboard.view'],
    ]);

    $this->actingAs($user)
        ->getJson(route('api.permissions.snapshot'))
        ->assertOk()
        ->assertJsonPath('permissions.0', 'dashboard.view')
        ->assertJsonCount(1, 'menus')
        ->assertJsonCount(1, 'applications');

    $this->getJson(route('api.permissions.version'))
        ->assertOk()
        ->assertJsonPath('permission_version', 1);
});

test('tenant admin cannot manage records in another tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['applications.manage']);

    $application = Application::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($admin)
        ->put(route('applications.update', $application), [
            'code' => 'blocked',
            'name' => 'Blocked',
            'client_id' => $application->client_id,
            'base_url' => 'https://blocked.example.test',
            'redirect_uri' => 'https://blocked.example.test/callback',
            'status' => 'active',
            'required_permissions' => [],
        ])
        ->assertForbidden();
});

test('audit logs can be filtered by action', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['audit_logs.view']);

    AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'action' => 'sso.code_issued',
        'ip_address' => '127.0.0.1',
    ]);
    AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'action' => 'role.updated',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($admin)
        ->get(route('audit-logs.index', ['search' => 'sso']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.data.0.action', 'sso.code_issued')
            ->has('items.data', 1));
});
