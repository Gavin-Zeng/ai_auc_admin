<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
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
        $tenant = app(TenantContext::class)->current() ?? app(TenantContext::class)->resolveForRequest($request);

        return User::query()
            ->whereHas('tenantMemberships', fn (Builder $query) => $query->where('tenant_id', $tenant?->id))
            ->with(['tenantMemberships' => fn ($query) => $query->where('tenant_id', $tenant?->id), 'roles']);
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'users',
            'label' => '用户管理',
            'description' => '维护当前租户成员、成员状态和角色授权。',
            'createLabel' => '新增用户',
            'storeUrl' => route('users.store'),
            'fields' => [
                ['name' => 'account', 'label' => '账号', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => '邮箱', 'type' => 'text', 'required' => true],
                ['name' => 'password', 'label' => '密码', 'type' => 'text'],
                ['name' => 'status', 'label' => '租户成员状态', 'type' => 'select', 'options' => ['active', 'disabled']],
                ['name' => 'role_ids', 'label' => '角色', 'type' => 'multiselect'],
            ],
            'columns' => ['account', 'name', 'email', 'is_platform_admin'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        $tenant = app(TenantContext::class)->current();

        return [
            'role_ids' => Role::query()
                ->where('tenant_id', $tenant?->id)
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Role $role) => ['value' => $role->id, 'label' => "{$role->code} - {$role->name}"])
                ->values(),
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'account' => ['required', 'string', 'min:2', 'max:18', 'regex:/^[A-Za-z]+$/', $this->unique('auc_users', 'account', $model)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', $this->unique('auc_users', 'email', $model)],
            'password' => [$model === null ? 'required' : 'nullable', 'string', 'min:8'],
            'status' => ['required', 'in:active,disabled'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:auc_roles,id'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        unset($data['status'], $data['role_ids']);

        if (($data['password'] ?? null) === null && $model !== null) {
            unset($data['password']);
        }

        return $data;
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        TenantUser::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $model->id,
        ], [
            'status' => $request->string('status')->toString() ?: 'active',
        ]);

        $roleIds = collect($request->input('role_ids', []))
            ->map(fn ($roleId) => (int) $roleId)
            ->filter()
            ->all();

        $model->roles()->wherePivot('tenant_id', $tenant->id)->detach();

        foreach ($roleIds as $roleId) {
            $model->roles()->attach($roleId, ['tenant_id' => $tenant->id]);
        }

        $permissionVersion->bump($tenant);
    }
}
