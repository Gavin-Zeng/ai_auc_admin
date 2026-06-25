<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Role::class;
    }

    protected function resourceQuery(Request $request): Builder
    {
        $tenant = app(TenantContext::class)->current() ?? app(TenantContext::class)->resolveForRequest($request);

        return Role::query()
            ->where('tenant_id', $tenant?->id)
            ->with(['permissions', 'tenant:id,name']);
    }

    protected function resourceConfig(Request $request): array
    {
        $fields = [
            ['name' => 'code', 'label' => '编码', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
            ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
        ];

        if ($request->user()?->isPlatformAdmin()) {
            $fields[] = ['name' => 'tenant_id', 'label' => '所属公司', 'type' => 'select', 'required' => true, 'createOnly' => true, 'span' => 1, 'group' => '公司归属'];
        }

        $fields = [
            ...$fields,
            ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled'], 'default' => 'active', 'updateOnly' => true, 'span' => 1, 'group' => '公司归属'],
            ['name' => 'permission_ids', 'label' => '权限', 'type' => 'multiselect', 'span' => 2, 'group' => '权限授权'],
        ];

        return [
            'name' => 'roles',
            'label' => '角色管理',
            'description' => '按当前公司维护角色，并绑定可授予的权限集合。',
            'createLabel' => '新增角色',
            'storeUrl' => route('roles.store'),
            'fields' => $fields,
            'columns' => ['company_name', 'code', 'name', 'status', 'is_system'],
        ];
    }

    protected function transformItems(EloquentCollection $items, Request $request): void
    {
        $items->each(function (Role $role): void {
            $role->setAttribute('company_name', $role->tenant?->name ?? '-');
        });
    }

    protected function resourceOptions(Request $request): array
    {
        $isPlatformAdmin = $request->user()?->isPlatformAdmin();

        return [
            'tenant_id' => $isPlatformAdmin
                ? Tenant::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'status'])
                    ->map(fn (Tenant $tenant) => [
                        'value' => (string) $tenant->id,
                        'label' => $tenant->status === 'active'
                            ? $tenant->name
                            : "{$tenant->name}（已停用）",
                    ])
                    ->values()
                : [],
            'permission_ids' => Permission::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Permission $permission) => ['value' => $permission->id, 'label' => "{$permission->code} - {$permission->name}"])
                ->values(),
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:120'],
            'status' => [$model === null ? 'nullable' : 'required', 'in:active,disabled'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:auc_permissions,id'],
            'tenant_id' => $this->tenantRules($request, $model),
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        $data['tenant_id'] = $this->resolveTenantId($request, $model);
        $data['status'] ??= 'active';
        unset($data['permission_ids']);

        return $data;
    }

    /**
     * @return list<mixed>
     */
    private function tenantRules(Request $request, ?Model $model = null): array
    {
        if ($model !== null) {
            return ['prohibited'];
        }

        if ($request->user()?->isPlatformAdmin()) {
            return ['required', 'integer', Rule::exists('auc_tenants', 'id')];
        }

        return ['prohibited'];
    }

    private function resolveTenantId(Request $request, ?Model $model = null): int
    {
        if ($model !== null) {
            return (int) $model->tenant_id;
        }

        if ($request->user()?->isPlatformAdmin()) {
            return (int) $request->integer('tenant_id');
        }

        return (int) app(TenantContext::class)->current()?->id;
    }

    protected function authorizeResourceModel(Model $model, mixed $tenant): void
    {
        abort_unless((int) $model->tenant_id === (int) $tenant->id, 403);
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        $model->permissions()->sync($request->input('permission_ids', []));
        $permissionVersion->bump($tenant);
    }
}
