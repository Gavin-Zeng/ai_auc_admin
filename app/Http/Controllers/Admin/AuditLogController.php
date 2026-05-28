<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);
        abort_if($tenant === null, 403);
        $search = $request->string('search')->toString();

        return Inertia::render('admin/ResourceIndex', [
            'resource' => [
                'name' => 'audit-logs',
                'label' => '审计日志',
                'description' => '查看登录、SSO、授权和后台管理变更记录。',
                'readOnly' => true,
                'fields' => [],
                'columns' => ['action', 'subject_type', 'subject_id', 'ip_address', 'created_at'],
            ],
            'items' => AuditLog::query()
                ->where('tenant_id', $tenant->id)
                ->when($search !== '', fn ($query) => $query->where('action', 'like', "%{$search}%"))
                ->with('user:id,name,email')
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'filters' => ['search' => $search],
            'options' => [],
        ]);
    }
}
