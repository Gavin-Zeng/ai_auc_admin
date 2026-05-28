<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('auc:diagnose')]
#[Description('检查 AUC 默认租户、权限、菜单、角色和应用配置是否完整')]
class AucDiagnose extends Command
{
    /**
     * @var list<string>
     */
    private const RequiredPermissions = [
        'dashboard.view',
        'tenants.manage',
        'users.manage',
        'roles.manage',
        'permissions.manage',
        'menus.manage',
        'applications.manage',
        'audit_logs.view',
    ];

    /**
     * @var list<string>
     */
    private const RequiredMenus = [
        'dashboard',
        'tenants',
        'users',
        'roles',
        'permissions',
        'menus',
        'applications',
        'audit_logs',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failed = false;

        $tenant = Tenant::query()->where('code', 'default')->first();
        $failed = $this->check($tenant !== null, '默认租户 default 存在') || $failed;

        $permissionCodes = Permission::query()->pluck('code')->all();
        foreach (self::RequiredPermissions as $permission) {
            $failed = $this->check(in_array($permission, $permissionCodes, true), "权限 {$permission} 存在") || $failed;
        }

        if ($tenant !== null) {
            $menuCodes = Menu::query()->where('tenant_id', $tenant->id)->pluck('code')->all();
            foreach (self::RequiredMenus as $menu) {
                $failed = $this->check(in_array($menu, $menuCodes, true), "默认租户菜单 {$menu} 存在") || $failed;
            }
        }

        $role = $tenant === null ? null : Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'admin')
            ->first();
        $failed = $this->check($role !== null, '默认 admin 角色存在') || $failed;

        if ($role !== null) {
            $rolePermissions = $role->permissions()->pluck('auc_permissions.code')->all();
            foreach (self::RequiredPermissions as $permission) {
                $failed = $this->check(in_array($permission, $rolePermissions, true), "admin 角色包含 {$permission}") || $failed;
            }
        }

        $application = $tenant === null ? null : Application::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'auc-admin')
            ->first();
        $failed = $this->check($application !== null, '默认应用 auc-admin 存在') || $failed;
        $failed = $this->check($application?->redirect_uri !== null && str_contains($application->redirect_uri, '/demo-subsystem/sso/callback'), '默认应用回调地址指向 demo 子系统') || $failed;

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(bool $passed, string $message): bool
    {
        if ($passed) {
            $this->components->info($message);

            return false;
        }

        $this->components->error($message);

        return true;
    }
}
