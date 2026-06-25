<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\Permission;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                'label' => '操作日志',
                'description' => '查看登录、SSO、授权和后台管理操作记录。',
                'readOnly' => true,
                'fields' => [],
                'columns' => ['operator_name', 'operated_at', 'company_name', 'system_name', 'operation_action', 'operation_object', 'request_params', 'ip_address'],
            ],

            'items' => $this->logs($request, $search, $tenant->id),
            'filters' => ['search' => $search],
            'options' => [],
        ]);
    }

    private function logs(Request $request, string $search, int $tenantId): mixed
    {
        $query = AuditLog::query()
            ->with(['user:id,name,email', 'tenant:id,name'])
            ->when(! $request->user()?->isPlatformAdmin(), fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('tenant', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $systemNames = $this->systemNames($query->getCollection());

        $query->getCollection()->transform(function (AuditLog $log) use ($systemNames): array {
            $metadata = is_array($log->metadata) ? $log->metadata : [];

            return [
                'id' => $log->id,
                'operator_name' => $log->user?->name ?? $log->user?->email ?? '系统',
                'operated_at' => $log->created_at?->format('Y-m-d H:i:s') ?? '-',
                'company_name' => $log->tenant?->name ?? '-',
                'system_name' => $systemNames[$log->id] ?? '-',
                'operation_action' => $log->action,
                'operation_object' => $this->operationObject($log),
                'request_params' => $this->requestParams($metadata),
                'ip_address' => $log->ip_address ?? '-',
            ];
        });

        return $query;
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array<int, string>
     */
    private function systemNames(Collection $logs): array
    {
        $applicationIds = $logs
            ->filter(fn (AuditLog $log) => $log->subject_type === Application::class && $log->subject_id !== null)
            ->pluck('subject_id')
            ->all();
        $menuIds = $logs
            ->filter(fn (AuditLog $log) => $log->subject_type === Menu::class && $log->subject_id !== null)
            ->pluck('subject_id')
            ->all();
        $permissionIds = $logs
            ->filter(fn (AuditLog $log) => $log->subject_type === Permission::class && $log->subject_id !== null)
            ->pluck('subject_id')
            ->all();

        $applications = Application::query()
            ->whereIn('id', $applicationIds)
            ->pluck('name', 'id');
        $menus = Menu::query()
            ->whereIn('id', $menuIds)
            ->with('application:id,name')
            ->get(['id', 'application_id'])
            ->mapWithKeys(fn (Menu $menu) => [$menu->id => $menu->application?->name ?? '-']);
        $permissions = Permission::query()
            ->whereIn('id', $permissionIds)
            ->with('application:id,name')
            ->get(['id', 'application_id'])
            ->mapWithKeys(fn (Permission $permission) => [$permission->id => $permission->application?->name ?? '-']);

        return $logs
            ->mapWithKeys(function (AuditLog $log) use ($applications, $menus, $permissions): array {
                $name = match ($log->subject_type) {
                    Application::class => $applications->get($log->subject_id, '-'),
                    Menu::class => $menus->get($log->subject_id, '-'),
                    Permission::class => $permissions->get($log->subject_id, '-'),
                    default => '-',
                };

                return [$log->id => $name];
            })
            ->all();
    }

    private function operationObject(AuditLog $log): string
    {
        if ($log->subject_type === null || $log->subject_id === null) {
            return '-';
        }

        return class_basename($log->subject_type).'#'.$log->subject_id;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function requestParams(array $metadata): string
    {
        $params = $metadata['request'] ?? [];

        if (! is_array($params) || $params === []) {
            return '-';
        }

        return json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
    }
}
