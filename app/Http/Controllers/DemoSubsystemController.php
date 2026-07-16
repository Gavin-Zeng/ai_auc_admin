<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AucAuthorization;
use App\Support\DemoSubsystemSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class DemoSubsystemController extends Controller
{
    public function callback(Request $request, DemoSubsystemSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $application = Application::query()
            ->where('client_id', 'auc-admin')
            ->firstOrFail();

        $response = Http::asJson()
            ->timeout(5)
            ->connectTimeout(3)
            ->post(route('sso.token'), [
                'client_id' => $application->client_id,
                'client_secret' => config('auc.demo_client_secret', 'secret'),
                'code' => $validated['code'],
                'redirect_uri' => $application->redirect_uri,
            ]);

        if ($response->failed()) {
            return redirect()
                ->route('demo-subsystem.login-required')
                ->with('status', $response->json('message', 'SSO 换票失败。'));
        }

        $payload = $response->json();
        $session->put($request, [
            'auc_user_id' => $payload['user']['id'],
            'user' => $payload['user'],
            'tenant' => $payload['tenant'],
            'roles' => $payload['roles'],
            'permissions' => $payload['permissions'],
            'permission_version' => $payload['permission_version'],
            'session_expires_at' => $payload['session_expires_at'],
        ]);

        return redirect()->route('demo-subsystem.dashboard');
    }

    public function dashboard(Request $request, DemoSubsystemSession $session): Response|RedirectResponse
    {
        $identity = $session->identity($request);

        if ($identity === null || $session->isExpired($request)) {
            $session->forget($request);

            return redirect()->route('demo-subsystem.login-required');
        }

        return Inertia::render('demo-subsystem/Dashboard', [
            'identity' => $identity,
            'canViewReports' => $session->hasPermission($request, 'dashboard.view'),
        ]);
    }

    public function reports(Request $request, DemoSubsystemSession $session): Response
    {
        return Inertia::render('demo-subsystem/Reports', [
            'identity' => $session->identity($request),
        ]);
    }

    public function refreshPermissions(Request $request, DemoSubsystemSession $session, AucAuthorization $authorization): RedirectResponse
    {
        $identity = $session->identity($request);

        if ($identity === null) {
            return redirect()->route('demo-subsystem.login-required');
        }

        $user = User::query()->find($identity['auc_user_id'] ?? null);
        $tenant = Tenant::query()->find(data_get($identity, 'tenant.id'));

        if ($user === null || $tenant === null || ! $tenant->isActive()) {
            $session->forget($request);

            return redirect()
                ->route('demo-subsystem.login-required')
                ->with('status', 'AUC 身份或租户状态已失效，请重新从工作台进入。');
        }

        $snapshot = $authorization->identity($user, $tenant);

        $session->refresh($request, [
            'tenant' => $tenant->only(['id', 'code', 'name', 'status']),
            'roles' => $snapshot['roles'],
            'permissions' => $snapshot['permissions'],
            'permission_version' => $snapshot['permission_version'],
        ]);

        return back()->with('status', '权限快照已刷新。');
    }

    public function loginRequired(): Response
    {
        return Inertia::render('demo-subsystem/LoginRequired');
    }

    public function logout(Request $request, DemoSubsystemSession $session): RedirectResponse
    {
        $session->forget($request);

        return redirect()->route('demo-subsystem.login-required');
    }
}
