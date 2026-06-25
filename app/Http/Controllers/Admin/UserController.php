<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->with([
                'tenantMemberships' => fn ($query) => $query->where('tenant_id', $tenant?->id),
                'tenants' => fn ($query) => $query->orderBy('auc_tenants.name'),
                'roles' => fn ($query) => $query->wherePivot('tenant_id', $tenant?->id)->orderBy('auc_roles.code'),
            ])
            ->addSelect([
                'status' => TenantUser::query()
                    ->select('status')
                    ->whereColumn('auc_tenant_users.user_id', 'auc_users.id')
                    ->where('tenant_id', $tenant?->id)
                    ->limit(1),
                'is_owner' => TenantUser::query()
                    ->select('is_owner')
                    ->whereColumn('auc_tenant_users.user_id', 'auc_users.id')
                    ->where('tenant_id', $tenant?->id)
                    ->limit(1),
            ]);
    }

    protected function resourceConfig(Request $request): array
    {
        $tenant = app(TenantContext::class)->current();
        $fields = [
            ['name' => 'account', 'label' => '账号', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
            ['name' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
            ['name' => 'email', 'label' => '邮箱', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
            ['name' => 'password', 'label' => '密码', 'type' => 'text', 'span' => 1, 'group' => '基础信息'],
        ];

        if ($request->user()?->isPlatformAdmin()) {
            $fields[] = ['name' => 'tenant_id', 'label' => '所属公司', 'type' => 'select', 'required' => true, 'createOnly' => true, 'span' => 1, 'group' => '公司与状态'];
        }

        $fields = [
            ...$fields,
            ['name' => 'status', 'label' => '成员状态', 'type' => 'select', 'options' => ['active', 'disabled'], 'default' => 'active', 'span' => 1, 'group' => '公司与状态'],
            ['name' => 'is_owner', 'label' => '公司超管', 'type' => 'checkbox', 'span' => 1, 'group' => '公司与状态'],
            ['name' => 'role_ids', 'label' => '角色', 'type' => 'multiselect', 'span' => 2, 'group' => '角色授权'],
        ];

        return [
            'name' => 'users',
            'label' => '公司成员',
            'description' => '维护当前公司成员、成员状态和角色授权。',
            'createLabel' => '新增账号',
            'storeUrl' => route('users.store'),
            'currentTenantId' => $tenant?->id,
            'fields' => $fields,
            'columns' => ['account', 'name', 'email', 'company_names', 'role_names', 'is_owner', 'is_platform_admin', 'status'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        $tenant = app(TenantContext::class)->current();
        $isPlatformAdmin = $request->user()?->isPlatformAdmin();
        $roleQuery = Role::query()
            ->where('status', 'active')
            ->when(! $isPlatformAdmin, fn (Builder $query) => $query->where('tenant_id', $tenant?->id))
            ->orderBy('tenant_id')
            ->orderBy('code');

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
            'role_ids' => $roleQuery
                ->get(['id', 'tenant_id', 'code', 'name'])
                ->map(fn (Role $role) => [
                    'value' => (string) $role->id,
                    'label' => "{$role->code} - {$role->name}",
                    'tenant_id' => (string) $role->tenant_id,
                ])
                ->values(),
        ];
    }

    protected function transformItems(EloquentCollection $items, Request $request): void
    {
        $items->each(function (User $user): void {
            $roles = $user->roles;

            $user->setAttribute('role_ids', $roles->pluck('id')->values()->all());
            $user->setAttribute('company_names', $user->tenants->pluck('name')->values()->all());
            $user->setAttribute(
                'role_names',
                $roles
                    ->map(fn (Role $role): string => "{$role->code} - {$role->name}")
                    ->values()
                    ->all(),
            );
        });
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'account' => ['required', 'string', 'min:2', 'max:18', 'regex:/^[A-Za-z]+$/', $this->unique('auc_users', 'account', $model)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', $this->unique('auc_users', 'email', $model)],
            'password' => [$model === null ? 'required' : 'nullable', 'string', 'min:8'],
            'tenant_id' => $this->tenantRules($request, $model),
            'status' => ['nullable', 'in:active,disabled'],
            'is_owner' => ['boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', $this->roleExistsRule($request, $model)],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        unset($data['tenant_id'], $data['status'], $data['is_owner'], $data['role_ids']);

        if (($data['password'] ?? null) === null && $model !== null) {
            unset($data['password']);
        }

        return $data;
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        if ($request->isMethod('delete')) {
            $permissionVersion->bump($tenant);

            return;
        }

        $this->authorizeCompanyOwnerChange($request, $model, $tenant);

        TenantUser::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $model->id,
        ], [
            'status' => $request->string('status')->toString() ?: 'active',
            'is_owner' => $request->boolean('is_owner'),
        ]);

        $roleIds = collect($request->input('role_ids', []))
            ->map(fn ($roleId) => (int) $roleId)
            ->filter()
            ->all();

        abort_unless(Role::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $roleIds)
            ->count() === count($roleIds), 403);

        $model->roles()->wherePivot('tenant_id', $tenant->id)->detach();

        foreach ($roleIds as $roleId) {
            $model->roles()->attach($roleId, ['tenant_id' => $tenant->id]);
        }

        $permissionVersion->bump($tenant);
    }

    /**
     * @return list<mixed>
     */
    private function tenantRules(Request $request, ?Model $model = null): array
    {
        if ($model !== null) {
            return ['prohibited'];
        }

        if (! $request->user()?->isPlatformAdmin()) {
            return ['prohibited'];
        }

        return [
            'required',
            'integer',
            Rule::exists('auc_tenants', 'id'),
        ];
    }

    private function roleExistsRule(Request $request, ?Model $model = null): mixed
    {
        $tenantId = $model === null && $request->user()?->isPlatformAdmin()
            ? $request->integer('tenant_id')
            : app(TenantContext::class)->current()?->id;

        return Rule::exists('auc_roles', 'id')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active');
    }

    protected function authorizeResourceModel(Model $model, mixed $tenant): void
    {
        abort_unless(TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $model->id)
            ->exists(), 403);
    }

    protected function disableResourceModel(Request $request, Model $model, mixed $tenant): void
    {
        $membership = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $model->id)
            ->firstOrFail();

        abort_if($membership->is_owner && ! $request->user()?->isPlatformAdmin(), 403);

        $membership->forceFill(['status' => 'disabled'])->save();
    }

    private function authorizeCompanyOwnerChange(Request $request, Model $model, mixed $tenant): void
    {
        $currentMembership = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $model->id)
            ->first();

        $isChangingOwner = $currentMembership === null
            ? $request->boolean('is_owner')
            : $currentMembership->is_owner !== $request->boolean('is_owner');

        abort_if($isChangingOwner && ! $request->user()?->isPlatformAdmin(), 403);
    }
}
