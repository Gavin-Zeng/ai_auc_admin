<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Permission;
use App\Support\AuditLogger;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Application::class;
    }

    protected function resourceQuery(Request $request): Builder
    {
        $tenant = app(TenantContext::class)->current() ?? app(TenantContext::class)->resolveForRequest($request);

        return Application::query()->where('tenant_id', $tenant?->id);
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'applications',
            'label' => '应用管理',
            'description' => '配置业务系统的 SSO 接入地址、密钥和访问权限。',
            'createLabel' => '新增应用',
            'storeUrl' => route('applications.store'),
            'fields' => [
                ['name' => 'code', 'label' => '编码', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true],
                ['name' => 'client_id', 'label' => '客户端 ID', 'type' => 'text', 'required' => true],
                ['name' => 'client_secret', 'label' => '客户端密钥', 'type' => 'text'],
                ['name' => 'base_url', 'label' => '基础地址', 'type' => 'text', 'required' => true],
                ['name' => 'redirect_uri', 'label' => '回调地址', 'type' => 'text', 'required' => true],
                ['name' => 'icon', 'label' => '图标', 'type' => 'text'],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => ['active', 'disabled']],
                ['name' => 'required_permissions', 'label' => '所需权限', 'type' => 'multiselect'],
            ],
            'columns' => ['code', 'name', 'client_id', 'base_url', 'status'],
            'actions' => ['rotateSecret'],
        ];
    }

    protected function resourceOptions(Request $request): array
    {
        return [
            'required_permissions' => Permission::query()->orderBy('code')->pluck('code')->values(),
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:120'],
            'client_id' => ['required', 'string', 'max:120', $this->unique('auc_applications', 'client_id', $model)],
            'client_secret' => [$model === null ? 'required' : 'nullable', 'string', 'max:200'],
            'base_url' => ['required', 'url', 'max:500'],
            'redirect_uri' => ['required', 'url', 'max:500'],
            'icon' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,disabled'],
            'required_permissions' => ['nullable', 'array'],
            'required_permissions.*' => ['string', 'exists:auc_permissions,code'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        $data['tenant_id'] = app(TenantContext::class)->current()?->id;
        $data['required_permissions'] ??= [];

        if (($data['client_secret'] ?? null) === null && $model !== null) {
            unset($data['client_secret']);
        }

        return $data;
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        $permissionVersion->bump($tenant);
    }

    public function rotateSecret(Request $request, Application $application, AuditLogger $auditLogger): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current() ?? app(TenantContext::class)->resolveForRequest($request);
        $this->authorizeResourceModel($application, $tenant);

        $secret = Str::password(32);
        $application->forceFill(['client_secret' => $secret])->save();
        $auditLogger->log($request, 'application.secret_rotated', $application, $tenant);

        return back()->with('secret', $secret);
    }
}
