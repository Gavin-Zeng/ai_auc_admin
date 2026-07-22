<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\SsoAuthCode;
use App\Support\AucAuthorization;
use App\Support\PermissionResolver;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SsoController extends Controller
{
    public function authorize(Request $request, TenantContext $tenantContext, AucAuthorization $authorization): RedirectResponse|Response
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
            'tenant_id' => ['nullable', 'integer'],
            'state' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $tenant = $tenantContext->resolveForSso($request);

        if ($user === null || $tenant === null || ! $tenant->isActive()) {
            return $this->errorPage('当前租户不可用，无法发起单点登录。', HttpResponse::HTTP_FORBIDDEN);
        }

        $application = Application::query()
            ->where('client_id', $validated['client_id'])
            ->first();

        if ($application === null || ! $application->isActive() || ! $tenant->applications()->whereKey($application)->exists()) {
            return $this->errorPage('应用不存在或已停用。', HttpResponse::HTTP_FORBIDDEN);
        }

        if (! $application->urls()->where('redirect_uri', $validated['redirect_uri'])->where('status', true)->exists()) {
            return $this->errorPage('应用回调地址不匹配。', HttpResponse::HTTP_FORBIDDEN);
        }

        if (! $authorization->canAccessApplication($user, $tenant, $application)) {
            return $this->errorPage('当前用户没有访问该应用的权限。', HttpResponse::HTTP_FORBIDDEN);
        }

        $code = SsoAuthCode::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'application_id' => $application->id,
            'code' => Str::random(64),
            'redirect_uri' => $validated['redirect_uri'],
            'state' => $validated['state'] ?? null,
            'expires_at' => now()->addMinutes(2),
        ]);

        return redirect()->away($this->callbackUrl($code));
    }

    public function token(Request $request, AucAuthorization $authorization, PermissionResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
        ]);

        $application = Application::query()
            ->where('client_id', $validated['client_id'])
            ->first();

        if ($application === null) {
            return $this->tokenError('invalid_client', '客户端凭据无效。', HttpResponse::HTTP_UNAUTHORIZED);
        }

        if (! Hash::check($validated['client_secret'], $application->client_secret)) {
            return $this->tokenError('invalid_client', '客户端凭据无效。', HttpResponse::HTTP_UNAUTHORIZED);
        }

        if (! $application->isActive()) {
            return $this->tokenError('application_disabled', '应用已停用。', HttpResponse::HTTP_FORBIDDEN);
        }

        if (! $application->urls()->where('redirect_uri', $validated['redirect_uri'])->where('status', true)->exists()) {
            return $this->tokenError('redirect_uri_mismatch', '回调地址不匹配。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $code = SsoAuthCode::query()
            ->with(['tenant', 'user'])
            ->where('application_id', $application->id)
            ->where('code', $validated['code'])
            ->first();

        if ($code === null || $code->redirect_uri !== $validated['redirect_uri']) {
            return $this->tokenError('invalid_code', '授权码无效。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($code->used_at !== null) {
            return $this->tokenError('code_already_used', '授权码已被兑换。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($code->expires_at->isPast()) {
            return $this->tokenError('code_expired', '授权码已过期。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $code->user->isActive()) {
            return $this->tokenError('user_disabled', '用户账号不可用。', HttpResponse::HTTP_FORBIDDEN);
        }

        if (! $code->tenant->isActive()) {
            return $this->tokenError('tenant_disabled', '租户不可用。', HttpResponse::HTTP_FORBIDDEN);
        }

        if (! $code->tenant->applications()->whereKey($application)->exists()) {
            return $this->tokenError('application_disabled', '当前公司未开通该应用。', HttpResponse::HTTP_FORBIDDEN);
        }

        if (! $authorization->canAccessApplication($code->user, $code->tenant, $application)) {
            return $this->tokenError('access_revoked', '用户访问权限已被收回。', HttpResponse::HTTP_FORBIDDEN);
        }

        $consumed = DB::transaction(fn (): int => SsoAuthCode::query()
            ->whereKey($code->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now(), 'updated_at' => now()]));

        if ($consumed !== 1) {
            return $this->tokenError('code_already_used', '授权码已被兑换。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $identity = $resolver->resolve($code->user, $code->tenant, $application);
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes((int) config('session.lifetime'));

        return response()->json([
            'user' => $code->user->only(['id', 'name', 'account']),
            'tenant' => $code->tenant->only(['id', 'name', 'status']),
            'membership_id' => $identity['membership_id'],
            'application' => $application->only(['id', 'name', 'client_id']),
            'roles' => $identity['roles'],
            'permissions' => $identity['permissions'],
            'permission_version' => $identity['permission_version'],
            'business_scopes' => $identity['business_scopes'],
            'menus' => $authorization->menusForApplication($code->user, $code->tenant, $application),
            'issued_at' => $issuedAt->getTimestamp(),
            'expires_at' => $expiresAt->getTimestamp(),
            'session_expires_at' => $expiresAt->toISOString(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        return response()->json([
            'message' => '已接收退出请求，子系统应清理本地 session。',
        ], 202);
    }

    private function errorPage(string $message, int $status): Response
    {
        return Inertia::render('sso/Error', [
            'message' => $message,
            'status' => $status,
        ])->toResponse(request())->setStatusCode($status);
    }

    private function tokenError(string $error, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'message' => $message,
        ], $status);
    }

    private function callbackUrl(SsoAuthCode $code): string
    {
        $query = http_build_query(array_filter([
            'code' => $code->code,
            'state' => $code->state,
        ], fn (?string $value): bool => $value !== null && $value !== ''));

        return $code->redirect_uri.(str_contains($code->redirect_uri, '?') ? '&' : '?').$query;
    }
}
