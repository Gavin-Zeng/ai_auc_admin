<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Game;
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
                'business_scopes' => $this->gameScopes($user, $tenant),
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
            'business_scopes' => $this->gameScopes($user, $tenant),
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

    private function gameScopes(User $user, Tenant $tenant): array
    {
        if (! $user->isActive() || ! $tenant->isActive() || $user->tenant_id !== $tenant->id) {
            return [];
        }
        $games = Game::query()->where('status', true)->whereNotNull('old_id')->whereNotNull('app_id')->get();
        $permissions = $user->gamePermissions()->where('status', true)->get();
        if ($permissions->contains('scope_type', 'ALL')) {
            return $games->map(fn (Game $game) => $this->scope($game, 'ALL'))->values()->all();
        }
        $motherKeys = $permissions->where('scope_type', 'MOTHER')->pluck('scope_key');
        $childKeys = $permissions->where('scope_type', 'CHILD')->pluck('scope_key');

        return $games->filter(fn (Game $game) => $motherKeys->contains($game->old_id) || $childKeys->contains($game->app_id))->map(fn (Game $game) => $this->scope($game, $motherKeys->contains($game->old_id) ? 'MOTHER' : 'CHILD'))->values()->all();
    }

    private function scope(Game $game, string $scopeType): array
    {
        return ['scope_type' => $scopeType, 'mother_game_id' => $game->old_id, 'mother_game_name' => $game->old_name, 'game_id' => $game->app_id, 'game_name' => $game->name, 'game_code' => $game->game];
    }
}
