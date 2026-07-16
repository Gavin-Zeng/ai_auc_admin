<?php

use App\Models\Application;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\AucDiagnostics;

test('auc diagnose passes when default seed data is complete', function () {
    $tenant = Tenant::factory()->create(['code' => 'default']);
    $owner = User::factory()->create();
    TenantUser::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'status' => 'active',
        'is_owner' => true,
        'permission_version' => 1,
    ]);

    $permissions = collect(AucDiagnostics::RequiredPermissions)
        ->map(fn (string $code) => Permission::factory()->create(['code' => $code]));

    collect(AucDiagnostics::RequiredMenus)->each(fn (string $code) => Menu::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => $code,
    ]));

    $role = Role::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'admin',
    ]);
    $role->permissions()->sync($permissions->pluck('id')->all());

    $application = Application::factory()->create([
        'client_id' => 'auc-admin',
        'redirect_uri' => 'http://localhost/demo-subsystem/sso/callback',
    ]);
    aucOpenApplication($tenant, $application);

    $this->artisan('auc:diagnose')->assertSuccessful();
});

test('auc diagnose fails when default data is incomplete', function () {
    $this->artisan('auc:diagnose')->assertFailed();
});

test('diagnostics page exposes readonly report', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->get(route('diagnostics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Diagnostics')
            ->has('report.checks'));
});

test('company administrators cannot access platform diagnostics', function () {
    $tenant = Tenant::factory()->create(['code' => 'default']);
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['diagnostics.view']);

    $this->actingAs($admin)
        ->get(route('diagnostics.index'))
        ->assertForbidden();
});
