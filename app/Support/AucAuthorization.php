<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Collection;

class AucAuthorization
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @return array{roles: list<string>, permissions: list<string>, permission_version: int}
     */
    public function identity(User $user, Tenant $tenant): array
    {
        $roles = $this->roles($user, $tenant);
        $permissions = $this->permissions($user, $tenant);

        return [
            'roles' => $roles->values()->all(),
            'permissions' => $permissions->values()->all(),
            'permission_version' => $this->permissionVersion($user, $tenant),
        ];
    }

    public function userCan(User $user, string $permission, ?Tenant $tenant = null): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $tenant ??= $this->tenantContext->current();

        if ($tenant === null || ! $this->tenantContext->canAccess($user, $tenant)) {
            return false;
        }

        return $this->permissions($user, $tenant)->contains($permission);
    }

    /**
     * @return Collection<int, string>
     */
    public function roles(User $user, Tenant $tenant): Collection
    {
        if ($user->isPlatformAdmin()) {
            return collect(['platform_admin']);
        }

        return $user->roles()
            ->wherePivot('tenant_id', $tenant->id)
            ->where('auc_roles.status', 'active')
            ->orderBy('auc_roles.code')
            ->pluck('auc_roles.code');
    }

    /**
     * @return Collection<int, string>
     */
    public function permissions(User $user, Tenant $tenant): Collection
    {
        if ($user->isPlatformAdmin()) {
            return collect(['*']);
        }

        return $user->roles()
            ->wherePivot('tenant_id', $tenant->id)
            ->where('auc_roles.status', 'active')
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->where('status', 'active')
            ->pluck('code')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @return list<array{id: int, code: string, title: string, href: string|null, icon: string|null, children: list<array<string, mixed>>}>
     */
    public function menus(User $user, Tenant $tenant): array
    {
        $permissions = $this->permissions($user, $tenant);

        return Menu::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Menu $menu) => $this->hasRequiredPermissions($user, $permissions, $menu->required_permissions ?? []))
            ->groupBy('parent_id')
            ->pipe(fn (Collection $grouped) => $this->buildMenuTree($grouped, null));
    }

    /**
     * @return list<array{id: int, code: string, name: string, base_url: string, icon: string|null, authorize_url: string}>
     */
    public function applications(User $user, Tenant $tenant): array
    {
        $permissions = $this->permissions($user, $tenant);

        return Application::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->filter(fn (Application $application) => $this->hasRequiredPermissions($user, $permissions, $application->required_permissions ?? []))
            ->map(fn (Application $application) => [
                'id' => $application->id,
                'code' => $application->code,
                'name' => $application->name,
                'base_url' => $application->base_url,
                'icon' => $application->icon,
                'authorize_url' => route('sso.authorize', [
                    'client_id' => $application->client_id,
                    'redirect_uri' => $application->redirect_uri,
                    'tenant_id' => $tenant->id,
                ]),
            ])
            ->values()
            ->all();
    }

    public function canAccessApplication(User $user, Tenant $tenant, Application $application): bool
    {
        if ((int) $application->tenant_id !== (int) $tenant->id || ! $application->isActive()) {
            return false;
        }

        return $this->hasRequiredPermissions(
            $user,
            $this->permissions($user, $tenant),
            $application->required_permissions ?? [],
        );
    }

    private function permissionVersion(User $user, Tenant $tenant): int
    {
        if ($user->isPlatformAdmin()) {
            return 1;
        }

        return TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->value('permission_version') ?? 1;
    }

    /**
     * @param  Collection<int, string>  $permissions
     * @param  list<string>  $requiredPermissions
     */
    private function hasRequiredPermissions(User $user, Collection $permissions, array $requiredPermissions): bool
    {
        if ($user->isPlatformAdmin() || $permissions->contains('*')) {
            return true;
        }

        if ($requiredPermissions === []) {
            return true;
        }

        return collect($requiredPermissions)->every(fn (string $permission) => $permissions->contains($permission));
    }

    /**
     * @param  Collection<int|string, Collection<int, Menu>>  $grouped
     * @return list<array<string, mixed>>
     */
    private function buildMenuTree(Collection $grouped, ?int $parentId): array
    {
        return $grouped
            ->get($parentId, collect())
            ->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'code' => $menu->code,
                'title' => $menu->title,
                'href' => $menu->href,
                'icon' => $menu->icon,
                'children' => $this->buildMenuTree($grouped, $menu->id),
            ])
            ->values()
            ->all();
    }
}
