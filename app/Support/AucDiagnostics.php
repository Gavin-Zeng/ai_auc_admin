<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Collection;

class AucDiagnostics
{
    /**
     * @var list<string>
     */
    public const RequiredPermissions = [
        'dashboard.view',
        'tenants.manage',
        'users.manage',
        'roles.manage',
        'permissions.manage',
        'menus.manage',
        'applications.manage',
        'audit_logs.view',
        'diagnostics.view',
    ];

    /**
     * @var list<string>
     */
    public const RequiredMenus = [
        'dashboard',
        'tenants',
        'users',
        'roles',
        'permissions',
        'menus',
        'applications',
        'audit_logs',
        'diagnostics',
    ];

    /**
     * @return array{passed: bool, checks: list<array{key: string, label: string, passed: bool, severity: string, detail: string|null}>}
     */
    public function report(): array
    {
        $tenant = Tenant::query()->where('code', 'default')->first();
        $application = $tenant === null ? null : Application::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'auc-admin')
            ->first();
        $adminRole = $tenant === null ? null : Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'admin')
            ->first();

        $checks = collect([
            $this->check('tenant.default', '默认公司 default 存在', $tenant !== null),
            $this->check('tenant.active', '默认公司处于启用状态', $tenant?->isActive() === true, $tenant?->status),
            $this->check('admin.user', '存在平台管理员或默认公司管理员', $this->hasAdministrator($tenant)),
            $this->check('role.admin', '默认 admin 角色存在', $adminRole !== null),
            $this->check('application.auc-admin', '默认应用 auc-admin 存在', $application !== null),
            $this->check('application.active', '默认应用处于启用状态', $application?->isActive() === true, $application?->status),
            $this->check('application.secret', '默认应用密钥已配置', filled($application?->client_secret)),
            $this->check(
                'application.redirect_uri',
                '默认应用回调地址指向 demo 子系统',
                $application?->redirect_uri !== null && str_contains($application->redirect_uri, '/demo-subsystem/sso/callback'),
                $application?->redirect_uri,
            ),
            $this->check('application.base_url', '默认应用基础地址已配置', filled($application?->base_url), $application?->base_url),
            $this->check('permission.version', '默认公司成员权限版本有效', $this->hasPermissionVersion($tenant)),
        ]);

        $checks = $checks
            ->merge($this->permissionChecks($adminRole))
            ->merge($this->menuChecks($tenant))
            ->values();

        return [
            'passed' => $checks->every(fn (array $check): bool => $check['passed']),
            'checks' => $checks->all(),
        ];
    }

    /**
     * @return Collection<int, array{key: string, label: string, passed: bool, severity: string, detail: string|null}>
     */
    private function permissionChecks(?Role $adminRole): Collection
    {
        $permissionCodes = Permission::query()->pluck('code')->all();
        $rolePermissions = $adminRole === null
            ? []
            : $adminRole->permissions()->pluck('auc_permissions.code')->all();

        return collect(self::RequiredPermissions)
            ->flatMap(fn (string $permission): array => [
                $this->check("permission.{$permission}", "权限 {$permission} 存在", in_array($permission, $permissionCodes, true)),
                $this->check("role.admin.{$permission}", "admin 角色包含 {$permission}", in_array($permission, $rolePermissions, true)),
            ]);
    }

    /**
     * @return Collection<int, array{key: string, label: string, passed: bool, severity: string, detail: string|null}>
     */
    private function menuChecks(?Tenant $tenant): Collection
    {
        $menuCodes = $tenant === null
            ? []
            : Menu::query()->where('tenant_id', $tenant->id)->pluck('code')->all();

        return collect(self::RequiredMenus)
            ->map(fn (string $menu): array => $this->check(
                "menu.{$menu}",
                "默认公司菜单 {$menu} 存在",
                in_array($menu, $menuCodes, true),
            ));
    }

    private function hasAdministrator(?Tenant $tenant): bool
    {
        if (User::query()->where('is_platform_admin', true)->exists()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('is_owner', true)
            ->exists();
    }

    private function hasPermissionVersion(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        return TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('permission_version', '>=', 1)
            ->exists();
    }

    /**
     * @return array{key: string, label: string, passed: bool, severity: string, detail: string|null}
     */
    private function check(string $key, string $label, bool $passed, ?string $detail = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'severity' => $passed ? 'ok' : 'error',
            'detail' => $detail,
        ];
    }
}
