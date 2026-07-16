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
                'operation_action' => $this->operationAction($log->action),
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
        $allSubjectIds = $logs
            ->pluck('subject_id')
            ->filter()
            ->values()
            ->all();
        $applicationIds = $logs
            ->filter(fn (AuditLog $log) => $this->matchesSubjectType($log, Application::class) && $log->subject_id !== null)
            ->pluck('subject_id')
            ->all();
        $menuIds = $logs
            ->filter(fn (AuditLog $log) => $this->matchesSubjectType($log, Menu::class) && $log->subject_id !== null)
            ->pluck('subject_id')
            ->all();
        $permissionIds = $logs
            ->filter(fn (AuditLog $log) => $this->matchesSubjectType($log, Permission::class) && $log->subject_id !== null)
            ->pluck('subject_id')
            ->all();

        $applications = Application::query()
            ->whereIn('id', array_values(array_unique([...$allSubjectIds, ...$applicationIds])))
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
                $name = '-';

                if ($this->matchesSubjectType($log, Application::class)) {
                    $name = $applications->get($log->subject_id, '-');
                } elseif ($this->matchesSubjectType($log, Menu::class)) {
                    $name = $menus->get($log->subject_id, '-');
                } elseif ($this->matchesSubjectType($log, Permission::class)) {
                    $name = $permissions->get($log->subject_id, '-');
                } elseif ($log->subject_id !== null) {
                    $name = $applications->get($log->subject_id, '-');
                }

                return [$log->id => $name];
            })
            ->all();
    }

    private function matchesSubjectType(AuditLog $log, string $class): bool
    {
        if ($log->subject_type === null) {
            return false;
        }

        return $log->subject_type === $class
            || $log->subject_type === class_basename($class)
            || class_basename($log->subject_type) === class_basename($class);
    }

    private function operationObject(AuditLog $log): string
    {
        if ($log->subject_type === null || $log->subject_id === null) {
            return '-';
        }

        return class_basename($log->subject_type).'#'.$log->subject_id;
    }

    private function operationAction(string $action): string
    {
        return match ($action) {
            'sso.tenant_unavailable' => '租户不可用',
            'sso.application_unavailable' => '应用不可用',
            'sso.redirect_uri_rejected' => '回调地址被拒绝',
            'sso.application_access_denied' => '应用访问被拒绝',
            'sso.code_issued' => '签发授权码',
            'sso.token_client_not_found' => '客户端不存在',
            'sso.token_secret_invalid' => '客户端密钥无效',
            'sso.token_application_disabled' => '应用已停用',
            'sso.token_redirect_uri_rejected' => '回调地址不匹配',
            'sso.token_code_invalid' => '授权码无效',
            'sso.token_code_replayed' => '授权码重复兑换',
            'sso.token_code_expired' => '授权码已过期',
            'sso.token_tenant_disabled' => '租户已停用',
            'sso.code_exchanged' => '兑换授权码',
            'sso.logout_requested' => '请求退出',
            'application.secret_rotated' => '轮换系统密钥',
            'tenant.created' => '创建公司',
            'tenant.updated' => '更新公司',
            'tenant.disabled' => '停用公司',
            'role.created' => '创建角色',
            'role.updated' => '更新角色',
            'role.disabled' => '停用角色',
            'permission.created' => '创建权限',
            'permission.updated' => '更新权限',
            'permission.disabled' => '停用权限',
            'menu.created' => '创建菜单',
            'menu.updated' => '更新菜单',
            'menu.disabled' => '停用菜单',
            'application.created' => '创建系统',
            'application.updated' => '更新系统',
            'application.disabled' => '停用系统',
            'tenant_application.opened' => '开通公司系统',
            'user.created' => '创建账号',
            'user.updated' => '更新账号',
            'user.disabled' => '停用账号',
            default => $action,
        };
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
