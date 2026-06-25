<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Tenant::class;
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'tenants',
            'label' => '公司管理',
            'description' => '维护平台公司基础信息和启停状态，仅平台超管可操作。',
            'createLabel' => '新增公司',
            'storeUrl' => route('tenants.store'),
            'fields' => [
                ['name' => 'code', 'label' => '编码', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
                ['name' => 'name', 'label' => '公司名称', 'type' => 'text', 'required' => true, 'span' => 1, 'group' => '基础信息'],
                ['name' => 'domain', 'label' => '域名', 'type' => 'text', 'span' => 1, 'group' => '基础信息'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled'], 'default' => 'active', 'updateOnly' => true, 'span' => 1, 'group' => '状态'],
            ],
            'columns' => ['code', 'name', 'domain', 'status'],
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'code' => ['required', 'string', 'max:80', $this->unique('auc_tenants', 'code', $model)],
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['nullable', 'string', 'max:120', $this->unique('auc_tenants', 'domain', $model)],
            'status' => [$model === null ? 'nullable' : 'required', 'in:active,disabled'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        $data['status'] ??= 'active';

        return $data;
    }
}
