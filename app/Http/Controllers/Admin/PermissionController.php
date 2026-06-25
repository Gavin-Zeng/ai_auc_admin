<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Permission;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Permission::class;
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'permissions',
            'label' => '权限管理',
            'description' => '维护全局权限目录，角色按公司引用这些权限。',
            'createLabel' => '新增权限',
            'storeUrl' => route('permissions.store'),
            'fields' => [
                ['name' => 'code', 'label' => '编码', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true],
                ['name' => 'application_id', 'label' => '所属系统', 'type' => 'select'],
                ['name' => 'group', 'label' => '分组', 'type' => 'text'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled']],
                ['name' => 'description', 'label' => '描述', 'type' => 'textarea'],
            ],
            'columns' => ['code', 'name', 'application_id', 'group', 'status'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        $tenant = app(TenantContext::class)->current();

        return [
            'application_id' => Application::query()
                ->where('tenant_id', $tenant?->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Application $application) => [
                    'value' => $application->id,
                    'label' => $application->name,
                ])
                ->values(),
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'application_id' => ['nullable', 'integer', 'exists:auc_applications,id'],
            'code' => ['required', 'string', 'max:120', $this->unique('auc_permissions', 'code', $model)],
            'name' => ['required', 'string', 'max:120'],
            'group' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:active,disabled'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        $tenant = app(TenantContext::class)->current();

        if (($data['application_id'] ?? null) !== null) {
            abort_unless(Application::query()
                ->where('tenant_id', $tenant?->id)
                ->whereKey($data['application_id'])
                ->exists(), 403);
        }

        return $data;
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        $permissionVersion->bump($tenant);
    }
}
