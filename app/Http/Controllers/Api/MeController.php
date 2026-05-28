<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AucAuthorization;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext, AucAuthorization $authorization): JsonResponse
    {
        $user = $request->user();
        $tenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);

        abort_if($user === null || $tenant === null, 403);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'tenant' => $tenant->only(['id', 'code', 'name', 'status']),
            ...$authorization->identity($user, $tenant),
        ]);
    }
}
