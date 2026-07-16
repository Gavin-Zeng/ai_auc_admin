<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\SsoAuthCode;
use App\Support\AucAuthorization;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SsoController extends Controller
{
    public function authorize(Request $request, TenantContext $tenantContext, AucAuthorization $authorization, AuditLogger $auditLogger): RedirectResponse|Response
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
            'tenant_id' => ['nullable', 'integer'],
            'tenant_code' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $tenant = $tenantContext->resolveForSso($request);

        if ($user === null || $tenant === null || ! $tenant->isActive()) {
            $auditLogger->log($request, 'sso.tenant_unavailable', tenant: $tenant, metadata: [
                'client_id' => $validated['client_id'],
                'tenant_id' => $validated['tenant_id'] ?? null,
                'tenant_code' => $validated['tenant_code'] ?? null,
            ]);

            return $this->errorPage('当前租户不可用，无法发起单点登录。', HttpResponse::HTTP_FORBIDDEN);
        }

        $application = Application::query()
            ->where('client_id', $validated['client_id'])
            ->first();

        $tenantApplication = $application === null ? null : $authorization->tenantApplication($tenant, $application);

        if ($application === null || ! $application->isActive() || $tenantApplication === null || ! $tenantApplication->isActive()) {
            $auditLogger->log($request, 'sso.application_unavailable', $application, $tenant, [
                'client_id' => $validated['client_id'],
            ]);

            return $this->errorPage('应用不存在或已停用。', HttpResponse::HTTP_FORBIDDEN);
        }

        if ($validated['redirect_uri'] !== $application->redirect_uri) {
            $auditLogger->log($request, 'sso.redirect_uri_rejected', $application, $tenant, [
                'requested_redirect_uri' => $validated['redirect_uri'],
            ]);

            return $this->errorPage('应用回调地址不匹配。', HttpResponse::HTTP_FORBIDDEN);
        }

        if (! $authorization->canAccessApplication($user, $tenant, $application)) {
            $auditLogger->log($request, 'sso.application_access_denied', $application, $tenant, [
                'user_id' => $user->id,
            ]);

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

        $auditLogger->log($request, 'sso.code_issued', $application, $tenant, [
            'redirect_uri' => $validated['redirect_uri'],
        ]);

        return redirect()->away($this->callbackUrl($code));
    }

    public function token(Request $request, AucAuthorization $authorization, AuditLogger $auditLogger): JsonResponse
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
            $auditLogger->log($request, 'sso.token_client_not_found', metadata: [
                'client_id' => $validated['client_id'],
            ]);

            return $this->tokenError('invalid_client', '客户端凭据无效。', HttpResponse::HTTP_UNAUTHORIZED);
        }

        if (! Hash::check($validated['client_secret'], $application->client_secret)) {
            $auditLogger->log($request, 'sso.token_secret_invalid', $application, metadata: [
                'client_id' => $validated['client_id'],
            ]);

            return $this->tokenError('invalid_client', '客户端凭据无效。', HttpResponse::HTTP_UNAUTHORIZED);
        }

        if (! $application->isActive()) {
            $auditLogger->log($request, 'sso.token_application_disabled', $application);

            return $this->tokenError('application_disabled', '应用已停用。', HttpResponse::HTTP_FORBIDDEN);
        }

        if ($validated['redirect_uri'] !== $application->redirect_uri) {
            $auditLogger->log($request, 'sso.token_redirect_uri_rejected', $application, metadata: [
                'requested_redirect_uri' => $validated['redirect_uri'],
            ]);

            return $this->tokenError('redirect_uri_mismatch', '回调地址不匹配。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $code = SsoAuthCode::query()
            ->with(['tenant', 'user'])
            ->where('application_id', $application->id)
            ->where('code', $validated['code'])
            ->first();

        if ($code === null || $code->redirect_uri !== $validated['redirect_uri']) {
            $auditLogger->log($request, 'sso.token_code_invalid', $application, metadata: [
                'code' => $validated['code'],
            ]);

            return $this->tokenError('invalid_code', '授权码无效。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($code->used_at !== null) {
            $auditLogger->log($request, 'sso.token_code_replayed', $application, $code->tenant, [
                'code_id' => $code->id,
                'user_id' => $code->user_id,
            ]);

            return $this->tokenError('code_already_used', '授权码已被兑换。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($code->expires_at->isPast()) {
            $auditLogger->log($request, 'sso.token_code_expired', $application, $code->tenant, [
                'code_id' => $code->id,
                'user_id' => $code->user_id,
            ]);

            return $this->tokenError('code_expired', '授权码已过期。', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $code->tenant->isActive()) {
            $auditLogger->log($request, 'sso.token_tenant_disabled', $application, $code->tenant, [
                'code_id' => $code->id,
                'user_id' => $code->user_id,
            ]);

            return $this->tokenError('tenant_disabled', '租户不可用。', HttpResponse::HTTP_FORBIDDEN);
        }

        $tenantApplication = $authorization->tenantApplication($code->tenant, $application);

        if ($tenantApplication === null || ! $tenantApplication->isActive()) {
            $auditLogger->log($request, 'sso.token_application_not_opened', $application, $code->tenant, [
                'code_id' => $code->id,
                'user_id' => $code->user_id,
            ]);

            return $this->tokenError('application_disabled', '当前公司未开通该应用。', HttpResponse::HTTP_FORBIDDEN);
        }

        $code->forceFill(['used_at' => now()])->save();
        $auditLogger->log($request, 'sso.code_exchanged', $application, $code->tenant, [
            'user_id' => $code->user_id,
        ]);

        $identity = $authorization->identity($code->user, $code->tenant);

        return response()->json([
            'user' => $code->user->only(['id', 'name', 'email']),
            'tenant' => $code->tenant->only(['id', 'code', 'name', 'status']),
            'roles' => $identity['roles'],
            'permissions' => $identity['permissions'],
            'permission_version' => $identity['permission_version'],
            'menus' => $authorization->menusForApplication($code->user, $code->tenant, $application),
            'session_expires_at' => now()->addMinutes((int) config('session.lifetime'))->toISOString(),
        ]);
    }

    public function logout(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $auditLogger->log($request, 'sso.logout_requested');

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
