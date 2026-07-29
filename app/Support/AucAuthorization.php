<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class AucAuthorization
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    public function identity(User $user, Tenant $tenant): array
    {
        return $this->resolver->resolve($user, $tenant);
    }

    public function userCan(User $user, string $permission, ?Tenant $tenant = null): bool
    {
        $tenant ??= $user->tenant;

        if ($permission === 'dashboard.view') {
            return $user->isActive();
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($tenant === null || ! $user->isCompanyAdmin($tenant)) {
            return false;
        }

        return in_array($permission, ['users.manage', 'roles.manage', 'game-permissions.manage'], true);
    }

    public function canAccessApplication(User $user, Tenant $tenant, Application $application): bool
    {
        return $this->resolver->canAccessApplication($user, $tenant, $application);
    }

    public function menus(User $user, Tenant $tenant): array
    {
        return $this->menuTree($this->resolver->menus($user, $tenant)->where('is_visible', true));
    }

    public function menusForApplication(User $user, Tenant $tenant, Application $application): array
    {
        return $this->menuTree($this->resolver->menus($user, $tenant, $application)->where('is_visible', true));
    }

    public function applications(User $user, Tenant $tenant): array
    {
        return $tenant->applications()
            ->where('auc_applications.status', true)
            ->with(['urls' => fn ($query) => $query->where('status', true)->orderByDesc('is_default')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Application $application) => $this->canAccessApplication($user, $tenant, $application))
            ->map(function (Application $application) use ($tenant): array {
                $url = $application->urls->first();

                return [
                    'id' => $application->id,
                    'client_id' => $application->client_id,
                    'name' => $application->name,
                    'base_url' => $url?->base_url,
                    'status' => $application->status,
                    'is_available' => $url !== null,
                    'action_url' => $url === null ? null : route('sso.authorize', [
                        'client_id' => $application->client_id,
                        'redirect_uri' => $url->redirect_uri,
                        'tenant_id' => $tenant->id,
                    ]),
                ];
            })->values()->all();
    }

    public function dashboardApplications(User $user, ?Tenant $tenant): array
    {
        if (! $user->isPlatformAdmin()) {
            return $tenant === null ? [] : $this->applications($user, $tenant);
        }

        return Application::query()
            ->select(['id', 'client_id', 'name', 'status'])
            ->with(['urls' => fn ($query) => $query
                ->select(['id', 'application_id', 'base_url', 'redirect_uri', 'is_default'])
                ->where('status', true)
                ->orderByDesc('is_default'),
                'tenants' => fn ($query) => $query
                    ->select(['auc_tenants.id', 'auc_tenants.name', 'auc_tenants.status'])
                    ->where('auc_tenants.status', true)
                    ->orderBy('auc_tenants.id')])
            ->orderBy('name')
            ->get()
            ->map(function (Application $application) use ($tenant): array {
                $url = $application->urls->first();
                $accessTenant = $tenant !== null && $application->tenants->contains('id', $tenant->id)
                    ? $tenant
                    : $application->tenants->first();
                $isAvailable = $application->isActive() && $url !== null && $accessTenant !== null;

                return [
                    'id' => $application->id,
                    'client_id' => $application->client_id,
                    'name' => $application->name,
                    'base_url' => $url?->base_url,
                    'status' => $application->status,
                    'is_available' => $isAvailable,
                    'action_url' => $isAvailable ? route('sso.authorize', [
                        'client_id' => $application->client_id,
                        'redirect_uri' => $url->redirect_uri,
                        'tenant_id' => $accessTenant->id,
                    ]) : null,
                ];
            })->values()->all();
    }

    private function menuTree(Collection $menus, ?int $parentId = null): array
    {
        return $menus->where('parent_id', $parentId)->map(fn (Menu $menu) => [
            'id' => $menu->id,
            'code' => $menu->path ?? 'menu-'.$menu->id,
            'title' => $menu->name,
            'href' => $menu->path,
            'icon' => null,
            'children' => $this->menuTree($menus, $menu->id),
        ])->values()->all();
    }
}
