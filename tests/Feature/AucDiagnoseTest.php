<?php

use App\Models\Application;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;

test('auc diagnose passes when default seed data is complete', function () {
    $tenant = Tenant::factory()->create(['code' => 'default']);

    $permissions = collect([
        'dashboard.view',
        'tenants.manage',
        'users.manage',
        'roles.manage',
        'permissions.manage',
        'menus.manage',
        'applications.manage',
        'audit_logs.view',
    ])->map(fn (string $code) => Permission::factory()->create(['code' => $code]));

    collect([
        'dashboard',
        'tenants',
        'users',
        'roles',
        'permissions',
        'menus',
        'applications',
        'audit_logs',
    ])->each(fn (string $code) => Menu::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => $code,
    ]));

    $role = Role::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'admin',
    ]);
    $role->permissions()->sync($permissions->pluck('id')->all());

    Application::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'auc-admin',
        'redirect_uri' => 'http://localhost/demo-subsystem/sso/callback',
    ]);

    $this->artisan('auc:diagnose')->assertSuccessful();
});
