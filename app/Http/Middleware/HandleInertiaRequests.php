<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\AucAuthorization;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = app(TenantContext::class)->current();
        $membership = $user !== null && $tenant !== null
            ? app(TenantContext::class)->membership($user, $tenant)
            : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'tenant' => $tenant,
                'membership' => $membership?->only(['status', 'is_owner', 'permission_version']),
                'identity' => [
                    'is_platform_admin' => $user?->isPlatformAdmin() ?? false,
                    'is_company_owner' => $user !== null && $tenant !== null && $user->isCompanyOwner($tenant),
                ],
                'tenants' => fn () => $user === null ? [] : ($user->isPlatformAdmin()
                    ? Tenant::query()
                    : $user->tenants())
                    ->orderBy('name')
                    ->get(['auc_tenants.id', 'auc_tenants.code', 'auc_tenants.name', 'auc_tenants.status']),
            ],
            'auc' => fn () => $user === null || $tenant === null ? [
                'roles' => [],
                'permissions' => [],
                'permission_version' => 1,
                'menus' => [],
                'applications' => [],
            ] : [
                ...app(AucAuthorization::class)->identity($user, $tenant),
                'menus' => app(AucAuthorization::class)->menus($user, $tenant),
                'applications' => app(AucAuthorization::class)->applications($user, $tenant),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
