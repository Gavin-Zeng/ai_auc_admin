<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return User::class;
    }

    protected function resourceQuery(Request $request): Builder
    {
        $tenantId = $request->user()->isPlatformAdmin()
            ? ($request->integer('company_id') ?: null)
            : $request->user()->tenant_id;

        return User::query()->with(['tenant:id,name', 'role:id,name'])
            ->when(! $request->user()->isPlatformAdmin(), fn ($query) => $query->where('is_platform_admin', false))
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId));
    }

    protected function searchColumns(): array
    {
        return ['name', 'account'];
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'users', 'label' => '用户管理', 'description' => '创建用户、绑定一个公司和一个角色，并支持管理员重置密码。',
            'createLabel' => '新增用户', 'storeUrl' => route('users.store'),
            'fields' => [
                ['name' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true],
                ['name' => 'account', 'label' => '账号', 'type' => 'text', 'required' => true],
                ['name' => 'password', 'label' => '密码', 'type' => 'password', 'generatePassword' => true],
                ['name' => 'tenant_id', 'label' => '所属公司', 'type' => 'select'],
                ['name' => 'role_id', 'label' => '角色', 'type' => 'select'],
                ['name' => 'is_company_admin', 'label' => '公司超级管理员', 'type' => 'checkbox', 'default' => false],
                ['name' => 'is_platform_admin', 'label' => '平台超级管理员', 'type' => 'checkbox', 'default' => false, 'platformOnly' => true],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => [1, 0], 'default' => 1, 'updateOnly' => true],
            ],
            'columns' => ['name', 'account', 'company_name', 'role_name', 'is_company_admin', 'is_platform_admin', 'status', 'created_at'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        $tenantIds = $request->user()->isPlatformAdmin() ? Tenant::query()->pluck('id') : collect([$request->user()->tenant_id]);

        return [
            'tenant_id' => Tenant::query()->whereIn('id', $tenantIds)->orderBy('name')->get()->map(fn ($tenant) => ['value' => $tenant->id, 'label' => $tenant->name]),
            'role_id' => Role::query()->whereIn('tenant_id', $tenantIds)->where('status', true)->with('tenant:id,name')->orderBy('name')->get()->map(fn ($role) => ['value' => $role->id, 'label' => $role->tenant->name.' / '.$role->name]),
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'account' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{1,31}$/', $this->unique('auc_users', 'account', $model)],
            'password' => [$model === null ? 'required' : 'nullable', 'string', 'min:8'],
            'tenant_id' => ['nullable', 'integer', 'exists:auc_tenants,id'],
            'role_id' => ['nullable', 'integer', 'exists:auc_roles,id'],
            'is_company_admin' => ['boolean'], 'is_platform_admin' => ['boolean'], 'status' => [$model === null ? 'sometimes' : 'required', 'boolean'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        if ($model === null) {
            $data['status'] = true;
        }

        if (! $request->user()->isPlatformAdmin()) {
            $data['tenant_id'] = $request->user()->tenant_id;
            $data['is_platform_admin'] = false;
        }

        if ($data['is_platform_admin'] ?? false) {
            $data['tenant_id'] = null;
            $data['role_id'] = null;
            $data['is_company_admin'] = false;
        } else {
            abort_if(empty($data['tenant_id']), 422, '普通用户必须选择公司。');
            $role = isset($data['role_id']) ? Role::query()->find($data['role_id']) : null;
            abort_if($role !== null && $role->tenant_id !== (int) $data['tenant_id'], 422, '角色必须属于用户公司。');
            abort_if(! ($data['is_company_admin'] ?? false) && $role === null, 422, '普通用户必须选择角色。');
        }

        if ($model !== null && empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    protected function authorizeResourceModel(Model $model, mixed $tenant): void
    {
        abort_unless(request()->user()->isPlatformAdmin() || ($model->tenant_id === request()->user()->tenant_id && ! $model->is_platform_admin), 403);
        abort_if($model->is(request()->user()) && request()->boolean('status') === false, 422, '不能停用当前账号。');
    }

    protected function transformItems(Collection $items, Request $request): void
    {
        $items->each(function (User $user): void {
            $user->setAttribute('company_name', $user->tenant?->name ?? '平台');
            $user->setAttribute('role_name', $user->is_company_admin ? '公司超级管理员' : ($user->role?->name ?? '-'));
        });
    }
}
