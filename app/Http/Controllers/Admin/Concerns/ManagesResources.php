<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\AuditLogger;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

trait ManagesResources
{
    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);
        abort_if($tenant === null && ! $request->user()?->isPlatformAdmin(), 403);

        $query = $this->resourceQuery($request);
        $search = $request->string('search')->toString();

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                foreach ($this->searchColumns() as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $items = $query->latest('id')->paginate(10)->withQueryString();

        $this->transformItems($items->getCollection(), $request);

        return Inertia::render('admin/ResourceIndex', [
            'resource' => $this->resourceConfig($request),
            'items' => $items,
            'filters' => ['search' => $search],
            'options' => $this->resourceOptions($request),
        ]);
    }

    public function store(Request $request, TenantContext $tenantContext, AuditLogger $auditLogger, PermissionVersion $permissionVersion): RedirectResponse
    {
        $currentTenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);
        abort_if($currentTenant === null && ! $request->user()?->isPlatformAdmin(), 403);

        $data = $this->validated($request);
        $tenant = $this->tenantForWrite($request, $currentTenant);
        $model = $this->resourceModel()::query()->create($this->prepareData($request, $data));

        $this->afterWrite($request, $model, $tenant, $permissionVersion);
        $auditLogger->log($request, $this->auditAction('created'), $model, $tenant);

        return back()->with('status', $this->resourceLabel().'已创建。');
    }

    public function update(Request $request, TenantContext $tenantContext, AuditLogger $auditLogger, PermissionVersion $permissionVersion): RedirectResponse
    {
        $currentTenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);
        abort_if($currentTenant === null && ! $request->user()?->isPlatformAdmin(), 403);
        $model = $this->routeModel($request);
        $this->authorizeResourceModel($model, $currentTenant);

        $data = $this->validated($request, $model);
        $tenant = $this->tenantForWrite($request, $currentTenant, $model);
        $model->forceFill($this->prepareData($request, $data, $model))->save();

        $this->afterWrite($request, $model, $tenant, $permissionVersion);
        $auditLogger->log($request, $this->auditAction('updated'), $model, $tenant);

        return back()->with('status', $this->resourceLabel().'已更新。');
    }

    public function destroy(Request $request, TenantContext $tenantContext, AuditLogger $auditLogger, PermissionVersion $permissionVersion): RedirectResponse
    {
        $tenant = $tenantContext->current() ?? $tenantContext->resolveForRequest($request);
        abort_if($tenant === null, 403);
        $model = $this->routeModel($request);
        $this->authorizeResourceModel($model, $tenant);

        $this->disableResourceModel($request, $model, $tenant);

        $this->afterWrite($request, $model, $tenant, $permissionVersion);
        $auditLogger->log($request, $this->auditAction('disabled'), $model, $tenant);

        return back()->with('status', $this->resourceLabel().'已停用。');
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function resourceModel(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function resourceConfig(Request $request): array;

    /**
     * @return array<string, mixed>
     */
    protected function resourceOptions(Request $request): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    protected function resourceQuery(Request $request): Builder
    {
        return $this->resourceModel()::query();
    }

    protected function transformItems(EloquentCollection $items, Request $request): void
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Model $model = null): array
    {
        return $request->validate($this->rules($request, $model));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(Request $request, ?Model $model = null): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        return $data;
    }

    protected function tenantForWrite(Request $request, mixed $currentTenant, ?Model $model = null): mixed
    {
        return $currentTenant;
    }

    protected function authorizeResourceModel(Model $model, mixed $tenant): void
    {
        if (array_key_exists('tenant_id', $model->getAttributes())) {
            abort_unless((int) $model->tenant_id === (int) $tenant->id, 403);
        }
    }

    protected function disableResourceModel(Request $request, Model $model, mixed $tenant): void
    {
        if (array_key_exists('status', $model->getAttributes())) {
            $model->forceFill(['status' => 'disabled'])->save();
        } else {
            $model->delete();
        }
    }

    protected function afterWrite(Request $request, Model $model, mixed $tenant, PermissionVersion $permissionVersion): void
    {
        //
    }

    protected function resourceLabel(): string
    {
        return $this->resourceConfig(request())['label']
            ?? str(class_basename($this->resourceModel()))->headline()->toString();
    }

    protected function auditAction(string $verb): string
    {
        return str(class_basename($this->resourceModel()))->snake()->append('.', $verb)->toString();
    }

    protected function unique(string $table, string $column, ?Model $model = null): mixed
    {
        return Rule::unique($table, $column)->ignore($model?->getKey());
    }

    protected function routeModel(Request $request): Model
    {
        $parameter = collect($request->route()?->parameters() ?? [])->last();

        if ($parameter instanceof Model) {
            return $parameter;
        }

        return $this->resourceModel()::query()->findOrFail($parameter);
    }
}
