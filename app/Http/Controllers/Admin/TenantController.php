<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Tenant;
use App\Support\PermissionVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Tenant::class;
    }

    protected function resourceQuery(Request $request): Builder
    {
        return Tenant::query()->with('applications:id,name')->withCount('users');
    }

    protected function searchColumns(): array
    {
        return ['name'];
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'tenants', 'label' => '公司管理', 'description' => '维护公司、开通系统和启停状态。',
            'createLabel' => '新增公司', 'storeUrl' => route('tenants.store'),
            'fields' => [
                ['name' => 'name', 'label' => '公司名称', 'type' => 'text', 'required' => true],
                ['name' => 'application_ids', 'label' => '已开通系统', 'type' => 'multiselect'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => [1, 0], 'default' => 1, 'updateOnly' => true],
            ],
            'columns' => ['name', 'applications_text', 'users_count', 'status'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        return ['application_ids' => Application::query()->orderBy('name')->get()->map(fn (Application $application) => [
            'value' => $application->id, 'label' => $application->name,
        ])];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120', $this->unique('auc_tenants', 'name', $model)],
            'application_ids' => ['nullable', 'array'],
            'application_ids.*' => ['integer', 'exists:auc_applications,id'],
        ];

        if ($model !== null) {
            $rules['status'] = ['required', 'boolean'];
        }

        return $rules;
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        unset($data['application_ids']);

        if ($model === null) {
            $data['status'] = true;
        }

        return $data;
    }

    protected function transformItems(Collection $items, Request $request): void
    {
        $items->each(function (Tenant $tenant): void {
            $tenant->setAttribute('application_ids', $tenant->applications->modelKeys());
            $tenant->setAttribute('applications_text', $tenant->applications->pluck('name')->join('、') ?: '-');
        });
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        $applicationIds = collect($request->input('application_ids', []))->map(fn ($id) => (int) $id);
        $removedIds = $model->applications()->pluck('auc_applications.id')->diff($applicationIds);
        $model->applications()->sync($applicationIds);

        if ($removedIds->isNotEmpty()) {
            $model->roles()->with('menus')->get()->each(fn ($role) => $role->menus()->detach(
                $role->menus->whereIn('application_id', $removedIds)->modelKeys(),
            ));
        }
    }
}
