<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Menu::class;
    }

    protected function resourceQuery(Request $request): Builder
    {
        return Menu::query()->with(['application:id,name', 'parent:id,name']);
    }

    protected function searchColumns(): array
    {
        return ['name', 'path'];
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'menus', 'label' => '菜单管理', 'description' => '统一维护系统的两级菜单。',
            'createLabel' => '新增菜单', 'storeUrl' => route('menus.store'),
            'fields' => [
                ['name' => 'application_id', 'label' => '所属系统', 'type' => 'select', 'required' => true],
                ['name' => 'parent_id', 'label' => '父菜单', 'type' => 'select'],
                ['name' => 'name', 'label' => '菜单名称', 'type' => 'text', 'required' => true],
                ['name' => 'path', 'label' => '菜单路径', 'type' => 'text'],
                ['name' => 'is_visible', 'label' => '是否显示', 'type' => 'checkbox', 'default' => true],
                ['name' => 'sort_order', 'label' => '排序', 'type' => 'number', 'default' => 0],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => [1, 0], 'default' => 1, 'updateOnly' => true],
            ],
            'columns' => ['name', 'path', 'application_name', 'parent_name', 'is_visible', 'sort_order', 'status'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        return [
            'application_id' => Application::query()->orderBy('name')->get()->map(fn ($item) => ['value' => $item->id, 'label' => $item->name]),
            'parent_id' => Menu::query()->whereNull('parent_id')->orderBy('application_id')->orderBy('sort_order')->get()->map(fn ($item) => ['value' => $item->id, 'label' => $item->name]),
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        $rules = [
            'application_id' => ['required', 'integer', 'exists:auc_applications,id'],
            'parent_id' => ['nullable', 'integer', 'exists:auc_menus,id'],
            'name' => ['required', 'string', 'max:120'],
            'path' => ['nullable', 'string', 'max:255', Rule::unique('auc_menus', 'path')->where('application_id', $request->integer('application_id'))->ignore($model?->id)],
            'is_visible' => ['boolean'], 'sort_order' => ['required', 'integer', 'min:0'],
        ];

        if ($model !== null) {
            $rules['status'] = ['required', 'boolean'];
        }

        return $rules;
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        if ($model === null) {
            $data['status'] = true;
        }

        if (! empty($data['parent_id'])) {
            $parent = Menu::query()->findOrFail($data['parent_id']);
            abort_if($parent->parent_id !== null || $parent->application_id !== (int) $data['application_id'], 422, '只支持两级菜单，父菜单必须属于同一系统。');
        }

        return $data;
    }

    protected function transformItems(Collection $items, Request $request): void
    {
        $items->each(function (Menu $menu): void {
            $menu->setAttribute('application_name', $menu->application->name);
            $menu->setAttribute('parent_name', $menu->parent?->name ?? '-');
        });
    }
}
