<?php

use App\Models\Application;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SsoAuthCode;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\DemoSubsystemSession;
use Illuminate\Support\Facades\Http;

test('demo subsystem callback exchanges code and stores local session snapshot', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $application = Application::factory()->create([
        'client_id' => 'auc-admin',
        'client_secret' => 'secret',
        'redirect_uri' => 'http://localhost/demo-subsystem/sso/callback',
    ]);
    aucOpenApplication($tenant, $application, ['dashboard.view']);

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
        && $request['client_id'] === 'auc-admin'
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

test('demo subsystem can refresh local permission snapshot when permission version changes', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    $role = Role::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $permission = Permission::factory()->create([
        'code' => 'reports.export',
        'name' => 'reports.export',
        'status' => 'active',
    ]);
    $role->permissions()->attach($permission->id);
    TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->increment('permission_version');

    $this
        ->withSession([
            DemoSubsystemSession::SessionKey => [
                'auc_user_id' => $user->id,
                'user' => $user->only(['id', 'name', 'email']),
                'tenant' => $tenant->only(['id', 'code', 'name', 'status']),
                'roles' => ['operator'],
                'permissions' => ['dashboard.view'],
                'permission_version' => 1,
                'session_expires_at' => now()->addHour()->toISOString(),
            ],
        ])
        ->post(route('demo-subsystem.permissions.refresh'))
        ->assertRedirect()
        ->assertSessionHas(DemoSubsystemSession::SessionKey);

    $identity = session(DemoSubsystemSession::SessionKey);

    expect($identity['permission_version'])->toBe(2)
        ->and($identity['permissions'])->toContain('dashboard.view')
        ->and($identity['permissions'])->toContain('reports.export');
});

test('demo subsystem permission refresh requires local session', function () {
    $this
        ->post(route('demo-subsystem.permissions.refresh'))
        ->assertRedirect(route('demo-subsystem.login-required'));
});
