<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
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

        return Application::query()->where('tenant_id', $tenant?->id);
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'applications',
            'label' => '系统管理',
            'description' => '配置业务系统的 SSO 接入地址、密钥和访问权限。',
            'createLabel' => '新增系统',
            'storeUrl' => route('applications.store'),
            'fields' => [
                ['name' => 'code', 'label' => '编码', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true],
                ['name' => 'client_id', 'label' => '客户端 ID', 'type' => 'text', 'required' => true],
                ['name' => 'client_secret', 'label' => '客户端密钥', 'type' => 'text'],
                ['name' => 'base_url', 'label' => '基础地址', 'type' => 'text', 'required' => true],
                ['name' => 'redirect_uri', 'label' => '回调地址', 'type' => 'text', 'required' => true],
                ['name' => 'icon', 'label' => '图标', 'type' => 'text'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled']],
                ['name' => 'required_permissions', 'label' => '所需权限', 'type' => 'multiselect'],
            ],
            'columns' => ['code', 'name', 'client_id', 'base_url', 'status'],
            'actions' => ['rotateSecret'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        return [
            'required_permissions' => Permission::query()
                ->where(function (Builder $query): void {
                    $query->whereNull('application_id')
                        ->orWhereIn('application_id', Application::query()
                            ->where('tenant_id', app(TenantContext::class)->current()?->id)
                            ->select('id'));
                })
                ->orderBy('code')
                ->pluck('code')
                ->values(),
        ];
    }

    public function show(Request $request, Application $application, TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);
        abort_if($tenant === null, 403);
        $this->authorizeResourceModel($application, $tenant);

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
                ...$application->only(['id', 'tenant_id', 'code', 'name', 'client_id', 'base_url', 'redirect_uri', 'icon', 'required_permissions', 'status']),
                'secret_configured' => filled($application->client_secret),
            ],
            'tenant' => $tenant->only(['id', 'code', 'name', 'status']),
            'permissions' => $permissions,
            'menus' => $this->buildMenuTree($menus, null),
            'flatMenus' => $menus->values(),
            'authorization' => [
                'required_permissions' => $application->required_permissions ?? [],
                'roles' => $this->authorizedRoles($tenant->id, $application->required_permissions ?? []),
            ],
            'checks' => $this->integrationChecks($application, $tenant),
        ]);
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:120'],
            'client_id' => ['required', 'string', 'max:120', $this->unique('auc_applications', 'client_id', $model)],
            'client_secret' => [$model === null ? 'required' : 'nullable', 'string', 'max:200'],
            'base_url' => ['required', 'url', 'max:500'],
            'redirect_uri' => ['required', 'url', 'max:500'],
            'icon' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,disabled'],
            'required_permissions' => ['nullable', 'array'],
            'required_permissions.*' => ['string', 'exists:auc_permissions,code'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        $data['tenant_id'] = app(TenantContext::class)->current()?->id;
        $data['required_permissions'] ??= [];

        if (($data['client_secret'] ?? null) === null && $model !== null) {
            unset($data['client_secret']);
        }

        return $data;
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        $permissionVersion->bump($tenant);
    }

    public function rotateSecret(Request $request, Application $application, AuditLogger $auditLogger): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current() ?? app(TenantContext::class)->resolveForRequest($request);
        $this->authorizeResourceModel($application, $tenant);

        $secret = Str::password(32);
        $application->forceFill(['client_secret' => $secret])->save();
        $auditLogger->log($request, 'application.secret_rotated', $application, $tenant);

        return back()->with('secret', $secret);
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
