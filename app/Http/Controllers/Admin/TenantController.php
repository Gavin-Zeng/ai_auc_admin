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
                ['name' => 'code', 'label' => '编码', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => '公司名称', 'type' => 'text', 'required' => true],
                ['name' => 'domain', 'label' => '域名', 'type' => 'text'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled']],
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
            'status' => ['required', 'in:active,disabled'],
        ];
    }
}
