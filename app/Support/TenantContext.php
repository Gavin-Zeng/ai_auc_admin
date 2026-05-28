<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;

class TenantContext
{
    public const SessionKey = 'auc.current_tenant_id';

    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function resolveForRequest(Request $request): ?Tenant
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $tenant = $this->resolveRequestedTenant($request, $user)
            ?? $this->resolveSessionTenant($request, $user)
            ?? $this->resolveFirstTenant($user);

        $this->set($tenant);

        if ($tenant !== null) {
            $request->session()->put(self::SessionKey, $tenant->id);
        }

        return $tenant;
    }

    public function membership(User $user, Tenant $tenant): ?TenantUser
    {
        return TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function canAccess(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    private function resolveRequestedTenant(Request $request, User $user): ?Tenant
    {
        $tenantId = $request->integer('tenant_id') ?: null;
        $tenantCode = $request->string('tenant_code')->toString();

        if ($tenantId === null && $tenantCode === '') {
            return null;
        }

        $tenant = Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->whereKey($tenantId))
            ->when($tenantCode !== '', fn ($query) => $query->where('code', $tenantCode))
            ->first();

        if ($tenant === null || ! $this->canAccess($user, $tenant)) {
            abort(403);
        }

        return $tenant;
    }

    private function resolveSessionTenant(Request $request, User $user): ?Tenant
    {
        $tenantId = $request->session()->get(self::SessionKey);

        if ($tenantId === null) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null || ! $this->canAccess($user, $tenant)) {
            $request->session()->forget(self::SessionKey);

            return null;
        }

        return $tenant;
    }

    private function resolveFirstTenant(User $user): ?Tenant
    {
        if ($user->isPlatformAdmin()) {
            return Tenant::query()->orderBy('id')->first();
        }

        return Tenant::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', 'active'))
            ->orderBy('id')
            ->first();
    }
}
