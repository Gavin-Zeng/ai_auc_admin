<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class TenantContext
{
    private ?Tenant $tenant = null;

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

        $this->tenant = $user->tenant;

        return $this->tenant;
    }

    public function resolveForSso(Request $request): ?Tenant
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->isPlatformAdmin()) {
            $tenant = Tenant::query()->find($request->integer('tenant_id'));
            abort_if($tenant === null || ! $tenant->isActive(), 403);

            return $tenant;
        }

        return $this->resolveForRequest($request);
    }

    public function canAccess(User $user, Tenant $tenant): bool
    {
        return $user->isActive()
            && $tenant->isActive()
            && ($user->isPlatformAdmin() || $user->tenant_id === $tenant->id);
    }
}
