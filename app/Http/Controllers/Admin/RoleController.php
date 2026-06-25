<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

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
        return [
            'name' => 'roles',
            'label' => '角色管理',
            'description' => '按当前公司维护角色，并绑定可授予的权限集合。',
            'createLabel' => '新增角色',
            'storeUrl' => route('roles.store'),
            'fields' => [
                ['name' => 'code', 'label' => '编码', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled']],
                ['name' => 'permission_ids', 'label' => '权限', 'type' => 'multiselect'],
            ],
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
        return [
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
            'status' => ['required', 'in:active,disabled'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:auc_permissions,id'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        $data['tenant_id'] = app(TenantContext::class)->current()?->id;
        unset($data['permission_ids']);

        return $data;
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        $model->permissions()->sync($request->input('permission_ids', []));
        $permissionVersion->bump($tenant);
    }
}
