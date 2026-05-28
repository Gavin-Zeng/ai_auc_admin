<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:auc_tenants,id'],
        ]);

        $tenant = Tenant::query()->findOrFail($validated['tenant_id']);

        abort_unless(app(TenantContext::class)->canAccess($request->user(), $tenant), 403);

        $request->session()->put(TenantContext::SessionKey, $tenant->id);

        return back();
    }
}
