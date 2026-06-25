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
            'name' => '默认租户',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account' => 'testadmin',
            'name' => '测试用户',
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
            'dashboard.view' => '查看工作台',
            'tenants.manage' => '管理公司',
            'applications.manage' => '管理系统',
            'users.manage' => '管理用户',
            'roles.manage' => '管理角色',
            'permissions.manage' => '管理权限',
            'menus.manage' => '管理菜单',
            'audit_logs.view' => '查看操作日志',
            'diagnostics.view' => '查看运维诊断',
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
            'name' => '管理员',
            'status' => 'active',
            'is_system' => true,
        ]);

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => $tenant->id]]);

        Application::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'code' => 'auc-admin',
        ], [
            'name' => 'AUC 后台',
            'client_id' => 'auc-admin',
            'client_secret' => 'secret',
            'base_url' => config('app.url'),
            'redirect_uri' => config('app.url').'/demo-subsystem/sso/callback',
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
            ['tenant_id' => $tenantId, 'code' => 'dashboard', 'title' => '仪表盘', 'href' => '/dashboard', 'icon' => 'dashboard', 'required_permissions' => ['dashboard.view'], 'sort_order' => 10, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'tenants', 'title' => '公司管理', 'href' => '/tenants', 'icon' => 'tenants', 'required_permissions' => ['tenants.manage'], 'sort_order' => 20, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'users', 'title' => '公司成员', 'href' => '/users', 'icon' => 'users', 'required_permissions' => ['users.manage'], 'sort_order' => 30, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'roles', 'title' => '角色管理', 'href' => '/roles', 'icon' => 'roles', 'required_permissions' => ['roles.manage'], 'sort_order' => 40, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'permissions', 'title' => '权限管理', 'href' => '/permissions', 'icon' => 'permissions', 'required_permissions' => ['permissions.manage'], 'sort_order' => 50, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'menus', 'title' => '菜单管理', 'href' => '/menus', 'icon' => 'menus', 'required_permissions' => ['menus.manage'], 'sort_order' => 60, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'applications', 'title' => '系统管理', 'href' => '/applications', 'icon' => 'applications', 'required_permissions' => ['applications.manage'], 'sort_order' => 70, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'audit_logs', 'title' => '操作日志', 'href' => '/audit-logs', 'icon' => 'audit_logs', 'required_permissions' => ['audit_logs.view'], 'sort_order' => 80, 'is_visible' => true, 'status' => 'active'],
            ['tenant_id' => $tenantId, 'code' => 'diagnostics', 'title' => '运维诊断', 'href' => '/diagnostics', 'icon' => 'diagnostics', 'required_permissions' => ['diagnostics.view'], 'sort_order' => 90, 'is_visible' => true, 'status' => 'active'],
        ];
    }
}
