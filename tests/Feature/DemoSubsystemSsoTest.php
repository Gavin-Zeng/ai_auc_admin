<?php

use App\Models\Application;
use App\Models\SsoAuthCode;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DemoSubsystemSession;
use Illuminate\Support\Facades\Http;

test('demo subsystem callback exchanges code and stores local session snapshot', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'auc-admin',
        'client_id' => 'demo-client',
        'client_secret' => 'secret',
        'redirect_uri' => 'http://localhost/demo-subsystem/sso/callback',
        'required_permissions' => ['dashboard.view'],
    ]);

    SsoAuthCode::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'application_id' => $application->id,
        'code' => 'demo-code',
        'redirect_uri' => $application->redirect_uri,
        'expires_at' => now()->addMinute(),
    ]);

    Http::fake([
        route('sso.token') => Http::response([
            'user' => $user->only(['id', 'name', 'email']),
            'tenant' => $tenant->only(['id', 'code', 'name', 'status']),
            'roles' => ['operator'],
            'permissions' => ['dashboard.view'],
            'permission_version' => 1,
            'session_expires_at' => now()->addHour()->toISOString(),
        ]),
    ]);

    $this->get(route('demo-subsystem.callback', ['code' => 'demo-code']))
        ->assertRedirect(route('demo-subsystem.dashboard'))
        ->assertSessionHas(DemoSubsystemSession::SessionKey);

    Http::assertSent(fn ($request) => $request->url() === route('sso.token')
        && $request['client_id'] === 'demo-client'
        && $request['client_secret'] === 'secret'
        && $request['code'] === 'demo-code'
        && $request['redirect_uri'] === $application->redirect_uri);

    $identity = session(DemoSubsystemSession::SessionKey);

    expect($identity['auc_user_id'])->toBe($user->id)
        ->and($identity['tenant']['id'])->toBe($tenant->id)
        ->and($identity['permissions'])->toContain('dashboard.view');
});

test('demo subsystem local permission middleware protects reports', function () {
    $this->get(route('demo-subsystem.reports'))->assertForbidden();

    $this
        ->withSession([
            DemoSubsystemSession::SessionKey => [
                'user' => ['id' => 1, 'name' => 'Demo User', 'email' => 'demo@example.test'],
                'tenant' => ['id' => 1, 'code' => 'default', 'name' => '默认租户', 'status' => 'active'],
                'roles' => ['admin'],
                'permissions' => ['dashboard.view'],
                'permission_version' => 1,
                'session_expires_at' => now()->addHour()->toISOString(),
            ],
        ])
        ->get(route('demo-subsystem.reports'))
        ->assertOk();
});
