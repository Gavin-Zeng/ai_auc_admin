<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionResolver
{
    public function menus(User $user, Tenant $tenant, ?Application $application = null): Collection
    {
        if (! $this->canUseTenant($user, $tenant)) {
            return collect();
        }

        $openedApplicationIds = $tenant->applications()->where('auc_applications.status', true)->pluck('auc_applications.id');
        $query = Menu::query()
            ->whereIn('application_id', $openedApplicationIds)
            ->where('status', true)
            ->when($application, fn ($query) => $query->where('application_id', $application->id));

        if (! $user->isPlatformAdmin() && ! $user->isCompanyAdmin($tenant)) {
            if ($user->role === null || ! $user->role->status || $user->role->tenant_id !== $tenant->id) {
                return collect();
            }

            $query->whereHas('roles', fn ($query) => $query->whereKey($user->role_id));
        }

        return $query->with('application:id,name')->orderBy('application_id')->orderBy('sort_order')->get();
    }

    public function canAccessApplication(User $user, Tenant $tenant, Application $application): bool
    {
        if (! $application->isActive() || ! $tenant->isActive() || ! $tenant->applications()->whereKey($application)->exists()) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $this->menus($user, $tenant, $application)->isNotEmpty();
    }

    public function resolve(User $user, Tenant $tenant, ?Application $application = null): array
    {
        if ($user->isPlatformAdmin()) {
            return [
                'membership_id' => $user->tenant_id === $tenant->id ? $user->id : null,
                'roles' => ['platform_admin'],
                'permissions' => ['*'],
                'permission_version' => $user->updated_at?->getTimestamp() ?? 1,
                'business_scopes' => [],
                'permission_sources' => [],
                'truncated_permissions' => [],
            ];
        }

        $menus = $this->menus($user, $tenant, $application);

        return [
            'membership_id' => $user->tenant_id === $tenant->id ? $user->id : null,
            'roles' => $user->isCompanyAdmin($tenant)
                ? ['company_super_admin']
                : array_values(array_filter([$user->role?->name])),
            'permissions' => $menus->pluck('path')->filter()->unique()->values()->all(),
            'permission_version' => $user->updated_at?->getTimestamp() ?? 1,
            'business_scopes' => [],
            'permission_sources' => [],
            'truncated_permissions' => [],
        ];
    }

    public function can(User $user, Tenant $tenant, string $permission, ?Application $application = null): bool
    {
        return in_array($permission, $this->resolve($user, $tenant, $application)['permissions'], true);
    }

    private function canUseTenant(User $user, Tenant $tenant): bool
    {
        return $user->isActive() && $tenant->isActive()
            && ($user->isPlatformAdmin() || $user->tenant_id === $tenant->id);
    }
}
