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

        abort_if($user === null || $tenant === null, 403);

        return Inertia::render('Dashboard', [
            'tenant' => $tenant->only(['id', 'code', 'name', 'status']),
            'applications' => $authorization->applications($user, $tenant),
            'menus' => $authorization->menus($user, $tenant),
            'identity' => $authorization->identity($user, $tenant),
        ]);
    }
}
