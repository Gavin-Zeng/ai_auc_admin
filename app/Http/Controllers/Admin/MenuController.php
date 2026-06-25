<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Menu;
use App\Models\Permission;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Menu::class;
    }

    protected function resourceQuery(Request $request): Builder
    {
        $tenant = app(TenantContext::class)->current() ?? app(TenantContext::class)->resolveForRequest($request);

        return Menu::query()->where('tenant_id', $tenant?->id)->with(['parent', 'application']);
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'menus',
            'label' => '菜单管理',
            'description' => '维护当前公司菜单树、排序、显隐和权限绑定。',
            'createLabel' => '新增菜单',
            'storeUrl' => route('menus.store'),
            'fields' => [
                ['name' => 'code', 'label' => '编码', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
                ['name' => 'title', 'label' => '标题', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
                ['name' => 'href', 'label' => '链接', 'type' => 'text', 'span' => 1, 'group' => '展示'],
                ['name' => 'icon', 'label' => '图标', 'type' => 'text', 'span' => 1, 'group' => '展示'],
                ['name' => 'parent_id', 'label' => '父级菜单', 'type' => 'select', 'span' => 1, 'group' => '归属'],
                ['name' => 'application_id', 'label' => '所属系统', 'type' => 'select', 'span' => 1, 'group' => '归属'],
                ['name' => 'required_permissions', 'label' => '所需权限', 'type' => 'multiselect', 'span' => 2, 'group' => '授权'],
                ['name' => 'sort_order', 'label' => '排序', 'type' => 'number', 'span' => 1, 'group' => '状态'],
                ['name' => 'is_visible', 'label' => '是否显示', 'type' => 'checkbox', 'span' => 1, 'group' => '状态'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled'], 'default' => 'active', 'updateOnly' => true, 'span' => 1, 'group' => '状态'],
            ],
            'columns' => ['code', 'title', 'system_name', 'href', 'sort_order', 'is_visible', 'status'],
        ];
    }

    protected function transformItems(EloquentCollection $items, Request $request): void
    {
        $items->each(function (Menu $menu): void {
            $menu->setAttribute('system_name', $menu->application?->name ?? '-');
        });
    }

    protected function resourceOptions(Request $request): array
    {
        $tenant = app(TenantContext::class)->current();

        return [
            'parent_id' => Menu::query()->where('tenant_id', $tenant?->id)->orderBy('sort_order')->get(['id', 'title'])->map(fn (Menu $menu) => ['value' => $menu->id, 'label' => $menu->title])->values(),
            'application_id' => Application::query()->where('tenant_id', $tenant?->id)->orderBy('name')->get(['id', 'name'])->map(fn (Application $application) => ['value' => $application->id, 'label' => $application->name])->values(),
            'required_permissions' => Permission::query()->where('status', 'active')->orderBy('code')->pluck('code')->values(),
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'code' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:120'],
            'href' => ['nullable', 'string', 'max:300'],
            'icon' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:auc_menus,id'],
            'application_id' => ['nullable', 'integer', 'exists:auc_applications,id'],
            'required_permissions' => ['nullable', 'array'],
            'required_permissions.*' => ['string', 'exists:auc_permissions,code'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['boolean'],
            'status' => [$model === null ? 'nullable' : 'required', 'in:active,disabled'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        $tenant = app(TenantContext::class)->current();
        $data['tenant_id'] = $tenant?->id;
        $data['required_permissions'] ??= [];
        $data['is_visible'] = $request->boolean('is_visible');
        $data['sort_order'] ??= 0;
        $data['status'] ??= 'active';

        if (($data['parent_id'] ?? null) !== null) {
            abort_unless(Menu::query()
                ->where('tenant_id', $tenant?->id)
                ->whereKey($data['parent_id'])
                ->exists(), 403);
        }

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
