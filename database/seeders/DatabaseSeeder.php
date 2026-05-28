<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate([
            'code' => 'default',
        ], [
            'name' => 'Default Tenant',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        TenantUser::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ], [
            'status' => 'active',
            'is_owner' => true,
            'permission_version' => 1,
        ]);

        $permissionCodes = [
            'dashboard.view' => 'View dashboard',
            'applications.manage' => 'Manage applications',
            'users.manage' => 'Manage users',
            'roles.manage' => 'Manage roles',
            'menus.manage' => 'Manage menus',
            'audit_logs.view' => 'View audit logs',
        ];

        $permissions = collect($permissionCodes)
            ->map(fn (string $name, string $code) => Permission::query()->firstOrCreate([
                'code' => $code,
            ], [
                'name' => $name,
                'group' => str($code)->before('.')->toString(),
                'status' => 'active',
            ]));

        $role = Role::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'code' => 'admin',
        ], [
            'name' => 'Administrator',
            'status' => 'active',
            'is_system' => true,
        ]);

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => $tenant->id]]);

        Application::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'code' => 'auc-admin',
        ], [
            'name' => 'AUC Admin',
            'client_id' => 'auc-admin',
            'client_secret' => 'secret',
            'base_url' => config('app.url'),
            'redirect_uri' => config('app.url').'/sso/callback',
            'required_permissions' => ['dashboard.view'],
            'status' => 'active',
        ]);

        foreach ($this->menus($tenant->id) as $menu) {
            Menu::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'code' => $menu['code'],
            ], $menu);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function menus(int $tenantId): array
    {
        return [
            ['tenant_id' => $tenantId, 'code' => 'dashboard', 'title' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'dashboard', 'required_permissions' => ['dashboard.view'], 'sort_order' => 10, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'tenants', 'title' => 'Tenants', 'href' => '/tenants', 'icon' => 'tenants', 'required_permissions' => ['tenants.manage'], 'sort_order' => 20, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'users', 'title' => 'Users', 'href' => '/users', 'icon' => 'users', 'required_permissions' => ['users.manage'], 'sort_order' => 30, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'roles', 'title' => 'Roles', 'href' => '/roles', 'icon' => 'roles', 'required_permissions' => ['roles.manage'], 'sort_order' => 40, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'permissions', 'title' => 'Permissions', 'href' => '/permissions', 'icon' => 'permissions', 'required_permissions' => ['permissions.manage'], 'sort_order' => 50, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'menus', 'title' => 'Menus', 'href' => '/menus', 'icon' => 'menus', 'required_permissions' => ['menus.manage'], 'sort_order' => 60, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'applications', 'title' => 'Applications', 'href' => '/applications', 'icon' => 'applications', 'required_permissions' => ['applications.manage'], 'sort_order' => 70, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'audit_logs', 'title' => 'Audit Logs', 'href' => '/audit-logs', 'icon' => 'audit_logs', 'required_permissions' => ['audit_logs.view'], 'sort_order' => 80, 'is_visible' => true, 'status' => 'active'],
        ];
    }
}
