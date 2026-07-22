<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesResources;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationUrl;
use App\Support\PermissionVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    use ManagesResources;

    protected function resourceModel(): string
    {
        return Application::class;
    }

    protected function searchColumns(): array
    {
        return ['name', 'client_id'];
    }

    protected function resourceConfig(Request $request): array
    {
        return [
            'name' => 'applications', 'label' => '系统管理', 'description' => '维护系统、多域名回调和客户端凭证。',
            'createLabel' => '新增系统', 'storeUrl' => route('applications.store'),
            'fields' => [
                ['name' => 'name', 'label' => '系统名称', 'type' => 'text', 'required' => true],
                ['name' => 'base_url', 'label' => '默认系统地址', 'type' => 'text', 'required' => true],
                ['name' => 'redirect_uri', 'label' => '默认回调地址', 'type' => 'text', 'required' => true],
                ['name' => 'additional_urls', 'label' => '其他域名与回调', 'type' => 'textarea', 'description' => '每行一组：系统地址 | 回调地址', 'span' => 2],
                ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => [1, 0], 'default' => 1],
            ],
            'columns' => ['name', 'client_id', 'base_url', 'redirect_uri', 'urls_count', 'status'],
            'actions' => ['rotateSecret'],
        ];
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120', $this->unique('auc_applications', 'name', $model)],
            'base_url' => ['required', 'url', 'max:500'],
            'redirect_uri' => ['required', 'url', 'max:500'],
            'additional_urls' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'boolean'],
        ];
    }

    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        unset($data['base_url'], $data['redirect_uri'], $data['additional_urls']);

        if ($model === null) {
            $secret = Str::password(40);
            $data['client_id'] = (string) Str::uuid();
            $data['client_secret'] = Hash::make($secret);
            Inertia::flash('secret', $secret);
        }

        return $data;
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        ApplicationUrl::query()->updateOrCreate(
            ['application_id' => $model->id, 'is_default' => true],
            ['base_url' => $request->string('base_url'), 'redirect_uri' => $request->string('redirect_uri'), 'status' => true],
        );

        $additionalUrls = collect(preg_split('/\R/', $request->string('additional_urls')->toString()))
            ->map(fn (string $line): array => array_map('trim', explode('|', $line, 2)))
            ->filter(fn (array $parts): bool => count($parts) === 2 && filter_var($parts[0], FILTER_VALIDATE_URL) !== false && filter_var($parts[1], FILTER_VALIDATE_URL) !== false)
            ->values();
        $redirectUris = $additionalUrls->pluck(1);

        $model->urls()->where('is_default', false)->whereNotIn('redirect_uri', $redirectUris)->delete();
        $additionalUrls->each(fn (array $parts) => $model->urls()->updateOrCreate(
            ['redirect_uri' => $parts[1]],
            ['base_url' => $parts[0], 'is_default' => false, 'status' => true],
        ));
    }

    protected function transformItems(Collection $items, Request $request): void
    {
        $items->load('urls')->each(function (Application $application): void {
            $url = $application->urls->firstWhere('is_default', true) ?? $application->urls->first();
            $application->setAttribute('base_url', $url?->base_url);
            $application->setAttribute('redirect_uri', $url?->redirect_uri);
            $application->setAttribute('urls_count', $application->urls->count());
            $application->setAttribute('additional_urls', $application->urls->where('is_default', false)
                ->map(fn (ApplicationUrl $url): string => $url->base_url.' | '.$url->redirect_uri)->join("\n"));
        });
    }

    public function rotateSecret(Request $request, Application $application): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);
        $secret = Str::password(40);
        $application->update(['client_secret' => Hash::make($secret)]);
        Inertia::flash('secret', $secret);

        return back()->with('status', '客户端密钥已轮换，仅本次显示。');
    }
}
