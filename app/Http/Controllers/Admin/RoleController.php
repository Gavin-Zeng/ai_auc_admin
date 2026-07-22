<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\PermissionVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        $tenantId = $request->user()->isPlatformAdmin()
            ? ($request->integer('company_id') ?: null)
            : $request->user()->tenant_id;

        return Role::query()->with(['tenant:id,name', 'menus:id,name'])->withCount('users')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId));
    }

    protected function searchColumns(): array
    {
        return ['name'];
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'roles', 'label' => '角色管理', 'description' => '角色属于公司，并直接分配系统菜单。',
            'createLabel' => '新增角色', 'storeUrl' => route('roles.store'),
            'fields' => [
                ['name' => 'tenant_id', 'label' => '所属公司', 'type' => 'select', 'required' => true],
                ['name' => 'name', 'label' => '角色名称', 'type' => 'text', 'required' => true],
                ['name' => 'menu_ids', 'label' => '菜单权限', 'type' => 'multiselect'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => [1, 0], 'default' => 1, 'updateOnly' => true],
            ],
            'columns' => ['name', 'company_name', 'menus_text', 'users_count', 'status'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        $tenantIds = $request->user()->isPlatformAdmin()
            ? Tenant::query()->pluck('id')
            : collect([$request->user()->tenant_id]);
        $applicationIds = Tenant::query()->whereIn('id', $tenantIds)->with('applications:id')->get()
            ->flatMap->applications->pluck('id')->unique();

        return [
            'tenant_id' => Tenant::query()->whereIn('id', $tenantIds)->orderBy('name')->get()->map(fn ($tenant) => ['value' => $tenant->id, 'label' => $tenant->name]),
            'menu_ids' => Menu::query()->with('application:id,name')->whereIn('application_id', $applicationIds)->where('status', true)->orderBy('application_id')->orderBy('sort_order')->get()->map(fn ($menu) => [
                'value' => $menu->id, 'label' => $menu->application->name.' / '.$menu->name,
            ]),
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        $rules = [
            'tenant_id' => ['required', 'integer', Rule::exists('auc_tenants', 'id')],
            'name' => ['required', 'string', 'max:120', Rule::unique('auc_roles', 'name')->where('tenant_id', $request->integer('tenant_id'))->ignore($model?->id)],
            'menu_ids' => ['nullable', 'array'], 'menu_ids.*' => ['integer', 'exists:auc_menus,id'],
        ];

        if ($model !== null) {
            $rules['status'] = ['required', 'boolean'];
        }

        return $rules;
    }

    protected function tenantForWrite(Request $request, mixed $currentTenant, ?Model $model = null): Tenant
    {
        $tenant = Tenant::query()->findOrFail($request->integer('tenant_id'));
        abort_unless($request->user()->isPlatformAdmin() || $request->user()->tenant_id === $tenant->id, 403);

        return $tenant;
    }

    protected function authorizeResourceModel(Model $model, mixed $tenant): void
    {
        abort_unless(request()->user()->isPlatformAdmin() || request()->user()->tenant_id === $model->tenant_id, 403);
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        unset($data['menu_ids']);

        if ($model === null) {
            $data['status'] = true;
        }

        return $data;
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        $allowedMenuIds = $tenant->applications()->pluck('auc_applications.id')->pipe(fn ($ids) => Menu::query()->whereIn('application_id', $ids)->whereIn('id', $request->input('menu_ids', []))->pluck('id'));
        $model->menus()->sync($allowedMenuIds);
        $model->users()->touch();
    }

    protected function transformItems(Collection $items, Request $request): void
    {
        $items->each(function (Role $role): void {
            $role->setAttribute('company_name', $role->tenant->name);
            $role->setAttribute('menu_ids', $role->menus->modelKeys());
            $role->setAttribute('menus_text', $role->menus->pluck('name')->join('、') ?: '-');
        });
    }
}
