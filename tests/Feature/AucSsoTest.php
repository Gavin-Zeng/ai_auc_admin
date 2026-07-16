<?php

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\SsoAuthCode;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;

test('authenticated users can visit the AUC workspace', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create();
    aucOpenApplication($tenant, $application, ['dashboard.view']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('applications', 1));
});

test('guests visiting authorize are redirected to login', function () {
    $this->get(route('sso.authorize', [
        'client_id' => 'client',
        'redirect_uri' => 'https://client.test/callback',
    ]))->assertRedirect(route('login'));
});

test('inactive tenant cannot issue an authorization code', function () {
    $tenant = Tenant::factory()->create(['status' => 'disabled']);
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'redirect_uri' => 'https://client.test/callback',
    ]);
    aucOpenApplication($tenant, $application, ['dashboard.view']);

    $this->actingAs($user)
        ->get(route('sso.authorize', [
            'client_id' => $application->client_id,
            'redirect_uri' => $application->redirect_uri,
            'tenant_id' => $tenant->id,
        ]))
        ->assertForbidden();

    expect(AuditLog::query()->where('action', 'sso.tenant_unavailable')->exists())->toBeTrue();
});

test('users without application permissions cannot authorize', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'redirect_uri' => 'https://client.test/callback',
    ]);
    aucOpenApplication($tenant, $application, ['applications.manage']);

    $this->actingAs($user)
        ->get(route('sso.authorize', [
            'client_id' => $application->client_id,
            'redirect_uri' => $application->redirect_uri,
            'tenant_id' => $tenant->id,
        ]))
        ->assertForbidden();

    expect(AuditLog::query()->where('action', 'sso.application_access_denied')->exists())->toBeTrue();
});

test('tenant cannot authorize a global system until it is opened for that tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'redirect_uri' => 'https://client.test/callback',
    ]);

    $this->actingAs($user)
        ->get(route('sso.authorize', [
            'client_id' => $application->client_id,
            'redirect_uri' => $application->redirect_uri,
            'tenant_id' => $tenant->id,
        ]))
        ->assertForbidden();

    expect(AuditLog::query()->where('action', 'sso.application_unavailable')->exists())->toBeTrue();
});

test('disabled tenant application cannot authorize a global system', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'redirect_uri' => 'https://client.test/callback',
    ]);
    aucOpenApplication($tenant, $application, ['dashboard.view'], 'disabled');

    $this->actingAs($user)
        ->get(route('sso.authorize', [
            'client_id' => $application->client_id,
            'redirect_uri' => $application->redirect_uri,
            'tenant_id' => $tenant->id,
        ]))
        ->assertForbidden();

    expect(AuditLog::query()->where('action', 'sso.application_unavailable')->exists())->toBeTrue();
});

test('authorization code can only be exchanged once', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'client_secret' => 'plain-secret',
    ]);
    aucOpenApplication($tenant, $application, ['dashboard.view']);

    Menu::factory()->create([
        'tenant_id' => $tenant->id,
        'application_id' => $application->id,
        'title' => 'Client Dashboard',
        'required_permissions' => ['dashboard.view'],
    ]);

    $code = SsoAuthCode::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'application_id' => $application->id,
        'code' => 'one-time-code',
        'redirect_uri' => $application->redirect_uri,
        'expires_at' => now()->addMinute(),
    ]);

    $payload = [
        'client_id' => $application->client_id,
        'client_secret' => 'plain-secret',
        'code' => $code->code,
        'redirect_uri' => $application->redirect_uri,
    ];

    $this->postJson(route('sso.token'), $payload)
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('tenant.id', $tenant->id)
        ->assertJsonPath('permissions.0', 'dashboard.view')
        ->assertJsonPath('menus.0.title', 'Client Dashboard');

    $this->postJson(route('sso.token'), $payload)
        ->assertUnprocessable();

    expect(AuditLog::query()->where('action', 'sso.token_code_replayed')->exists())->toBeTrue();
});

test('expired authorization code cannot be exchanged', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'client_secret' => 'plain-secret',
    ]);
    aucOpenApplication($tenant, $application, ['dashboard.view']);

    SsoAuthCode::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'application_id' => $application->id,
        'code' => 'expired-code',
        'redirect_uri' => $application->redirect_uri,
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson(route('sso.token'), [
        'client_id' => $application->client_id,
        'client_secret' => 'plain-secret',
        'code' => 'expired-code',
        'redirect_uri' => $application->redirect_uri,
    ])->assertUnprocessable();

    expect(AuditLog::query()->where('action', 'sso.token_code_expired')->exists())->toBeTrue();
});

test('token exchange rejects invalid client secret and audits the failure', function () {
    $application = Application::factory()->create([
        'client_secret' => 'plain-secret',
    ]);

    $this->postJson(route('sso.token'), [
        'client_id' => $application->client_id,
        'client_secret' => 'wrong-secret',
        'code' => 'unused-code',
        'redirect_uri' => $application->redirect_uri,
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error', 'invalid_client');

    expect(AuditLog::query()->where('action', 'sso.token_secret_invalid')->exists())->toBeTrue();
});

test('token exchange rejects redirect uri mismatch', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'client_secret' => 'plain-secret',
        'redirect_uri' => 'https://client.test/callback',
    ]);
    aucOpenApplication($tenant, $application, ['dashboard.view']);

    SsoAuthCode::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'application_id' => $application->id,
        'code' => 'redirect-mismatch-code',
        'redirect_uri' => $application->redirect_uri,
        'expires_at' => now()->addMinute(),
    ]);

    $this->postJson(route('sso.token'), [
        'client_id' => $application->client_id,
        'client_secret' => 'plain-secret',
        'code' => 'redirect-mismatch-code',
        'redirect_uri' => 'https://client.test/other-callback',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error', 'redirect_uri_mismatch');

    expect(AuditLog::query()->where('action', 'sso.token_redirect_uri_rejected')->exists())->toBeTrue();
});

test('disabled application cannot exchange token', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'client_secret' => 'plain-secret',
        'status' => 'disabled',
    ]);
    aucOpenApplication($tenant, $application, ['dashboard.view']);

    SsoAuthCode::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'application_id' => $application->id,
        'code' => 'disabled-app-code',
        'redirect_uri' => $application->redirect_uri,
        'expires_at' => now()->addMinute(),
    ]);

    $this->postJson(route('sso.token'), [
        'client_id' => $application->client_id,
        'client_secret' => 'plain-secret',
        'code' => 'disabled-app-code',
        'redirect_uri' => $application->redirect_uri,
    ])
        ->assertForbidden()
        ->assertJsonPath('error', 'application_disabled');

    expect(AuditLog::query()->where('action', 'sso.token_application_disabled')->exists())->toBeTrue();
});

test('disabled tenant cannot exchange token and is audited', function () {
    $tenant = Tenant::factory()->create(['status' => 'disabled']);
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'client_secret' => 'plain-secret',
    ]);
    aucOpenApplication($tenant, $application, ['dashboard.view']);

    SsoAuthCode::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'application_id' => $application->id,
        'code' => 'disabled-tenant-code',
        'redirect_uri' => $application->redirect_uri,
        'expires_at' => now()->addMinute(),
    ]);

    $this->postJson(route('sso.token'), [
        'client_id' => $application->client_id,
        'client_secret' => 'plain-secret',
        'code' => 'disabled-tenant-code',
        'redirect_uri' => $application->redirect_uri,
    ])
        ->assertForbidden()
        ->assertJsonPath('error', 'tenant_disabled');

    expect(AuditLog::query()->where('action', 'sso.token_tenant_disabled')->exists())->toBeTrue();
});

test('tenant switch changes navigation and applications', function () {
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $firstTenant, ['dashboard.view']);
    aucGrant($user, $secondTenant, ['applications.manage']);

    $firstApplication = Application::factory()->create([
        'name' => 'First App',
    ]);
    aucOpenApplication($firstTenant, $firstApplication, ['dashboard.view']);

    $secondApplication = Application::factory()->create([
        'name' => 'Second App',
    ]);
    aucOpenApplication($secondTenant, $secondApplication, ['applications.manage']);

    $this->actingAs($user)
        ->post(route('tenant.switch'), ['tenant_id' => $secondTenant->id])
        ->assertRedirect();

    $this->getJson(route('api.navigation'))
        ->assertOk()
        ->assertJsonPath('applications.0.name', 'Second App');
});

test('sso tenant selection does not change the current admin tenant', function () {
    $currentTenant = Tenant::factory()->create();
    $ssoTenant = Tenant::factory()->create();
    $admin = User::factory()->platformAdmin()->create();
    $application = Application::factory()->create([
        'redirect_uri' => 'https://client.test/callback',
    ]);
    aucOpenApplication($ssoTenant, $application);

    $this->actingAs($admin)
        ->withSession([TenantContext::SessionKey => $currentTenant->id])
        ->get(route('sso.authorize', [
            'client_id' => $application->client_id,
            'redirect_uri' => $application->redirect_uri,
            'tenant_id' => $ssoTenant->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas(TenantContext::SessionKey, $currentTenant->id);

    expect(SsoAuthCode::query()->latest('id')->value('tenant_id'))->toBe($ssoTenant->id);
});
