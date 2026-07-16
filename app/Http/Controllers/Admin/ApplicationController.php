<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Support\AuditLogger;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Application::class;
    }

    protected function resourceQuery(Request $request): Builder
    {
        $tenant = app(TenantContext::class)->current() ?? app(TenantContext::class)->resolveForRequest($request);

        return Application::query()
            ->when(! $request->user()?->isPlatformAdmin(), fn (Builder $query) => $query
                ->whereHas('tenantApplications', fn (Builder $query) => $query
                    ->where('tenant_id', $tenant?->id)
                    ->where('status', 'active')));
    }

    protected function searchColumns(): array
    {
        return ['name', 'client_id', 'base_url'];
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'applications',
            'label' => '系统管理',
            'description' => '配置业务系统的 SSO 接入地址、密钥和访问权限。',
            'createLabel' => '新增系统',
            'storeUrl' => route('applications.store'),
            'readOnly' => ! $request->user()?->isPlatformAdmin(),
            'fields' => [
                ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
                ['name' => 'client_id', 'label' => '客户端 ID', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
                ['name' => 'client_secret', 'label' => '客户端密钥', 'type' => 'text', 'default' => '', 'createOnly' => true, 'span' => 2, 'group' => '基础信息'],
                ['name' => 'base_url', 'label' => '基础地址', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => 'SSO 配置'],
                ['name' => 'redirect_uri', 'label' => '回调地址', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => 'SSO 配置'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled'], 'default' => 'active', 'updateOnly' => true, 'span' => 1, 'group' => '展示'],
            ],
            'columns' => ['name', 'client_id', 'base_url', 'status'],
            'actions' => ['rotateSecret'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        return [
            'required_permissions' => Permission::query()
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Permission $permission) => [
                    'value' => $permission->code,
                    'label' => filled($permission->name)
                        ? "{$permission->code}（{$permission->name}）"
                        : $permission->code,
                ])
                ->values(),
        ];
    }

    public function show(Request $request, Application $application, TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);
        abort_if($tenant === null, 403);
        abort_unless($request->user()?->isPlatformAdmin() || $application->tenantApplications()->where('tenant_id', $tenant->id)->exists(), 403);

        $permissions = Permission::query()
            ->where('application_id', $application->id)
            ->orderBy('group')
            ->orderBy('code')
            ->get(['id', 'application_id', 'code', 'name', 'group', 'status', 'description']);

        $menus = Menu::query()
            ->where('tenant_id', $tenant->id)
            ->where('application_id', $application->id)
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'code', 'title', 'href', 'icon', 'required_permissions', 'sort_order', 'is_visible', 'status']);

        return Inertia::render('admin/ApplicationShow', [
            'application' => [
                ...$application->only(['id', 'name', 'client_id', 'base_url', 'redirect_uri', 'icon', 'status']),
                'secret_configured' => filled($application->client_secret),
            ],
            'tenant' => $tenant->only(['id', 'code', 'name', 'status']),
            'permissions' => $permissions,
            'menus' => $this->buildMenuTree($menus, null),
            'flatMenus' => $menus->values(),
            'authorization' => [
                'required_permissions' => $this->tenantApplication($tenant, $application)?->required_permissions ?? [],
                'roles' => $this->authorizedRoles($tenant->id, $this->tenantApplication($tenant, $application)?->required_permissions ?? []),
            ],
            'tenantApplications' => $this->tenantApplicationRows($application, $request->user()?->isPlatformAdmin() ? null : $tenant->id),
            'tenantOptions' => $request->user()?->isPlatformAdmin() ? $this->tenantOptions() : [],
            'permissionOptions' => $this->resourceOptions($request)['required_permissions'],
            'canManageTenantApplications' => $request->user()?->isPlatformAdmin() === true,
            'checks' => $this->integrationChecks($application, $tenant),
        ]);
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'client_id' => ['required', 'string', 'max:120', $this->unique('auc_applications', 'client_id', $model)],
            'client_secret' => [$model === null ? 'nullable' : 'prohibited', 'string', 'max:200'],
            'base_url' => ['required', 'url', 'max:500'],
            'redirect_uri' => ['required', 'url', 'max:500'],
            'icon' => ['nullable', 'string', 'max:120'],
            'status' => [$model === null ? 'nullable' : 'required', 'in:active,disabled'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        $data['status'] ??= 'active';
        $data['client_secret'] = filled($request->string('client_secret')->toString())
            ? $request->string('client_secret')->toString()
            : Str::password(32);

        if ($model !== null) {
            unset($data['client_secret']);
        }

        return $data;
    }

    protected function authorizeResourceModel(Model $model, mixed $tenant): void
    {
        abort_unless(request()->user()?->isPlatformAdmin(), 403);
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        Tenant::query()->each(fn (Tenant $tenant): mixed => $permissionVersion->bump($tenant));
    }

    public function rotateSecret(Request $request, Application $application, AuditLogger $auditLogger): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current() ?? app(TenantContext::class)->resolveForRequest($request);
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        $secret = Str::password(32);
        $application->forceFill(['client_secret' => $secret])->save();
        $auditLogger->log($request, 'application.secret_rotated', $application, $tenant);

        Inertia::flash('secret', $secret);

        return back();
    }

    public function openForTenant(Request $request, Application $application, AuditLogger $auditLogger, PermissionVersion $permissionVersion): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        $data = $request->validate([
            'target_tenant_id' => ['required', 'integer', 'exists:auc_tenants,id'],
            'required_permissions' => ['nullable', 'array'],
            'required_permissions.*' => ['string', 'exists:auc_permissions,code'],
            'status' => ['nullable', 'in:active,disabled'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $tenantApplication = TenantApplication::query()->updateOrCreate([
            'tenant_id' => $data['target_tenant_id'],
            'application_id' => $application->id,
        ], [
            'required_permissions' => $data['required_permissions'] ?? [],
            'status' => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $permissionVersion->bump($tenantApplication->tenant);
        $auditLogger->log($request, 'tenant_application.opened', $tenantApplication, $tenantApplication->tenant);

        return back()->with('status', '公司系统开通配置已保存。');
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return list<array<string, mixed>>
     */
    private function buildMenuTree(Collection $menus, ?int $parentId): array
    {
        return $menus
            ->where('parent_id', $parentId)
            ->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'code' => $menu->code,
                'title' => $menu->title,
                'href' => $menu->href,
                'icon' => $menu->icon,
                'required_permissions' => $menu->required_permissions ?? [],
                'sort_order' => $menu->sort_order,
                'is_visible' => $menu->is_visible,
                'status' => $menu->status,
                'children' => $this->buildMenuTree($menus, $menu->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $requiredPermissions
     * @return list<array{id: int, code: string, name: string, status: string}>
     */
    private function authorizedRoles(int $tenantId, array $requiredPermissions): array
    {
        if ($requiredPermissions === []) {
            return [];
        }

        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereHas('permissions', fn (Builder $query) => $query->whereIn('auc_permissions.code', $requiredPermissions))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'status'])
            ->map(fn (Role $role) => $role->only(['id', 'code', 'name', 'status']))
            ->values()
            ->all();
    }

    private function tenantApplication(mixed $tenant, Application $application): ?TenantApplication
    {
        return TenantApplication::query()
            ->where('tenant_id', $tenant->id)
            ->where('application_id', $application->id)
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantApplicationRows(Application $application, ?int $tenantId = null): array
    {
        return TenantApplication::query()
            ->with('tenant')
            ->where('application_id', $application->id)
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TenantApplication $tenantApplication) => [
                'id' => $tenantApplication->id,
                'tenant_id' => $tenantApplication->tenant_id,
                'tenant_name' => $tenantApplication->tenant?->name,
                'required_permissions' => $tenantApplication->required_permissions ?? [],
                'status' => $tenantApplication->status,
                'sort_order' => $tenantApplication->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function tenantOptions(): array
    {
        return Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn (Tenant $tenant) => [
                'value' => $tenant->id,
                'label' => $tenant->status === 'active'
                    ? $tenant->name
                    : "{$tenant->name}（已停用）",
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, passed: bool, message: string}>
     */
    private function integrationChecks(Application $application, mixed $tenant): array
    {
        return [
            [
                'label' => '系统状态',
                'passed' => $application->isActive(),
                'message' => $application->isActive() ? '系统已启用' : '系统已停用，无法发起 SSO。',
            ],
            [
                'label' => '公司状态',
                'passed' => $tenant->isActive(),
                'message' => $tenant->isActive() ? '当前公司可用' : '当前公司已停用，无法签发 code。',
            ],
            [
                'label' => '客户端 ID',
                'passed' => filled($application->client_id),
                'message' => filled($application->client_id) ? '已配置 client_id' : '缺少 client_id。',
            ],
            [
                'label' => '客户端密钥',
                'passed' => filled($application->client_secret),
                'message' => filled($application->client_secret) ? '已配置 secret，明文不可查看' : '缺少 secret，请轮换生成。',
            ],
            [
                'label' => '回调地址',
                'passed' => filled($application->redirect_uri),
                'message' => filled($application->redirect_uri) ? '已配置 redirect_uri' : '缺少 redirect_uri。',
            ],
        ];
    }
}
