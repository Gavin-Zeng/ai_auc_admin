<?php

namespace App\Http\Controllers;

use App\Support\AucAuthorization;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext, AucAuthorization $authorization): Response
    {
        $user = $request->user();
        $tenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);

        if ($user?->isPlatformAdmin() && $tenant === null) {
            return Inertia::render('Dashboard', [
                'tenant' => null,
                'applications' => $authorization->dashboardApplications($user, null),
                'menus' => [],
                'identity' => ['roles' => ['platform_admin'], 'permissions' => ['*'], 'permission_version' => 1],
            ]);
        }

        abort_if($user === null || $tenant === null, 403);

        return Inertia::render('Dashboard', [
            'tenant' => $tenant->only(['id', 'name', 'status']),
            'applications' => $authorization->dashboardApplications($user, $tenant),
            'menus' => $authorization->menus($user, $tenant),
            'identity' => $authorization->identity($user, $tenant),
        ]);
    }
}
