<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGamePermission;
use App\Support\PermissionVersion;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GamePermissionController extends Controller
{
    public function index(Request $request, TenantContext $context, ?User $user = null): Response
    {
        $tenant = $context->current() ?? $context->resolveForRequest($request);
        abort_if($tenant === null && ! $request->user()->isPlatformAdmin(), 403);
        $tenantId = $request->integer('company_id') ?: $tenant?->id ?: Tenant::query()->where('status', true)->value('id');
        abort_if($tenantId === null, 422, '请选择公司。');
        if ($user !== null) {
            abort_unless($user->tenant_id === $tenantId && ! $user->is_platform_admin, 404);
        }
        $games = Game::query()->where('status', true)->whereNotNull('old_id')->whereNotNull('app_id')->orderBy('old_name')->orderBy('name')->get(['old_id', 'old_name', 'app_id', 'name']);
        $user?->load(['gamePermissions' => fn ($query) => $query->where('status', true)]);
        $motherGames = $games->groupBy('old_id')->map(fn ($items, string $oldId) => [
            'id' => $oldId,
            'name' => $items->first()->old_name ?: $oldId,
            'children' => $items->map(fn (Game $game) => ['id' => $game->app_id, 'name' => $game->name])->values(),
        ])->values();
        $permissions = $user?->gamePermissions ?? collect();

        return Inertia::render('admin/GamePermissions', [
            'user' => $user?->only(['id', 'name', 'account']),
            'motherGames' => $motherGames,
            'permissions' => $permissions->map->only(['scope_type', 'scope_key']),
            'tenantId' => $tenantId,
        ]);
    }

    public function redirectToUsers(): RedirectResponse
    {
        return redirect()->route('users.index');
    }

    public function update(Request $request, User $user, TenantContext $context, PermissionVersion $version): RedirectResponse
    {
        $tenant = $context->current() ?? $context->resolveForRequest($request);
        $tenantId = $request->user()->isPlatformAdmin() ? $request->integer('tenant_id') : $tenant?->id;
        $data = $request->validate(['tenant_id' => ['required', 'integer', 'exists:auc_tenants,id'], 'permissions' => ['array'], 'permissions.*.scope_type' => ['required', Rule::in(['ALL', 'MOTHER', 'CHILD'])], 'permissions.*.scope_key' => ['nullable', 'string', 'max:80']]);
        abort_unless($user->tenant_id === $tenantId && ! $user->is_platform_admin, 404);
        abort_if($user->is_platform_admin, 403);
        $rows = collect($data['permissions'] ?? [])->filter(function (array $row): bool {
            if ($row['scope_type'] === 'ALL') {
                return true;
            }
            $scopeKey = $row['scope_key'] ?? null;
            if (! is_string($scopeKey) || $scopeKey === '') {
                return false;
            }

            return $row['scope_type'] === 'MOTHER'
                ? Game::query()->where('status', true)->where('old_id', $scopeKey)->exists()
                : Game::query()->where('status', true)->where('app_id', $scopeKey)->exists();
        })->map(fn (array $row) => ['tenant_id' => $tenantId, 'user_id' => $user->id, 'scope_type' => $row['scope_type'], 'scope_key' => $row['scope_type'] === 'ALL' ? '*' : $row['scope_key'], 'status' => true, 'created_at' => now(), 'updated_at' => now()])->unique(fn (array $row) => $row['scope_type'].'|'.$row['scope_key']);
        DB::transaction(function () use ($user, $rows): void {
            UserGamePermission::query()->where('user_id', $user->id)->delete();
            if ($rows->isNotEmpty()) {
                UserGamePermission::query()->insert($rows->all());
            }
        });
        $version->bump($user->tenant);

        return back()->with('status', '游戏权限已更新。');
    }
}
