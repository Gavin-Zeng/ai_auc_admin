<?php

use App\Models\Application;
use App\Models\ApplicationUrl;
use App\Models\SsoAuthCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('ordinary user can authorize only an opened system with an assigned menu', function () {
    [$user, $tenant, $role] = simpleCompanyUser();
    [$application, $url, $menu] = simpleApplication($tenant);
    $role->menus()->attach($menu);

    $this->actingAs($user)->get(route('sso.authorize', [
        'client_id' => $application->client_id,
        'redirect_uri' => $url->redirect_uri,
    ]))->assertRedirectContains($url->redirect_uri);
});

test('ordinary user without a menu cannot authorize the system', function () {
    [$user, $tenant] = simpleCompanyUser();
    [$application, $url] = simpleApplication($tenant);

    $this->actingAs($user)->get(route('sso.authorize', [
        'client_id' => $application->client_id,
        'redirect_uri' => $url->redirect_uri,
    ]))->assertForbidden();
});

test('company administrator receives all active menus of opened systems', function () {
    [$user, $tenant] = simpleCompanyUser(admin: true);
    [$application, $url, $menu] = simpleApplication($tenant);

    $this->actingAs($user)->get(route('sso.authorize', [
        'client_id' => $application->client_id,
        'redirect_uri' => $url->redirect_uri,
    ]))->assertRedirectContains($url->redirect_uri);

    expect($menu->exists)->toBeTrue();
});

test('platform administrator must explicitly select a company for sso', function () {
    $admin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create();
    [$application, $url] = simpleApplication($tenant);

    $this->actingAs($admin)->get(route('sso.authorize', [
        'client_id' => $application->client_id,
        'redirect_uri' => $url->redirect_uri,
    ]))->assertForbidden();

    $this->get(route('sso.authorize', [
        'client_id' => $application->client_id,
        'redirect_uri' => $url->redirect_uri,
        'tenant_id' => $tenant->id,
    ]))->assertRedirectContains($url->redirect_uri);
});

test('sso validates every redirect against the active multi-domain whitelist', function () {
    [$user, $tenant, $role] = simpleCompanyUser();
    [$application, $firstUrl, $menu] = simpleApplication($tenant);
    $secondUrl = ApplicationUrl::factory()->create(['application_id' => $application->id, 'is_default' => false]);
    $role->menus()->attach($menu);

    $this->actingAs($user)->get(route('sso.authorize', [
        'client_id' => $application->client_id,
        'redirect_uri' => $secondUrl->redirect_uri,
    ]))->assertRedirectContains($secondUrl->redirect_uri);

    $this->get(route('sso.authorize', [
        'client_id' => $application->client_id,
        'redirect_uri' => 'https://invalid.example/callback',
    ]))->assertForbidden();

    expect($firstUrl->exists)->toBeTrue();
});

test('authorization code is exchanged once and returns menu paths', function () {
    [$user, $tenant, $role] = simpleCompanyUser();
    [$application, $url, $menu] = simpleApplication($tenant);
    $role->menus()->attach($menu);
    $secret = 'plain-secret';
    $application->update(['client_secret' => Hash::make($secret)]);
    $code = SsoAuthCode::query()->create([
        'tenant_id' => $tenant->id, 'user_id' => $user->id, 'application_id' => $application->id,
        'code' => 'one-time-code', 'redirect_uri' => $url->redirect_uri, 'expires_at' => now()->addMinute(),
    ]);
    $payload = ['client_id' => $application->client_id, 'client_secret' => $secret, 'code' => $code->code, 'redirect_uri' => $url->redirect_uri];

    $this->postJson(route('sso.token'), $payload)->assertOk()
        ->assertJsonPath('user.account', $user->account)
        ->assertJsonPath('permissions.0', $menu->path)
        ->assertJsonPath('business_scopes', []);
    $this->postJson(route('sso.token'), $payload)->assertUnprocessable();
});

test('token exchange rejects wrong secret and redirect uri', function () {
    $application = Application::factory()->create(['client_secret' => Hash::make('secret')]);
    $url = ApplicationUrl::factory()->create(['application_id' => $application->id]);

    $this->postJson(route('sso.token'), [
        'client_id' => $application->client_id, 'client_secret' => 'wrong',
        'code' => 'unused', 'redirect_uri' => $url->redirect_uri,
    ])->assertUnauthorized();

    $this->postJson(route('sso.token'), [
        'client_id' => $application->client_id, 'client_secret' => 'secret',
        'code' => 'unused', 'redirect_uri' => 'https://invalid.example/callback',
    ])->assertUnprocessable();
});

test('disabled company user system or role blocks access immediately', function (string $target) {
    [$user, $tenant, $role] = simpleCompanyUser();
    [$application, $url, $menu] = simpleApplication($tenant);
    $role->menus()->attach($menu);
    match ($target) {
        'company' => $tenant->update(['status' => false]),
        'user' => $user->update(['status' => false]),
        'system' => $application->update(['status' => false]),
        'role' => $role->update(['status' => false]),
    };

    $this->actingAs($user)->get(route('sso.authorize', [
        'client_id' => $application->client_id, 'redirect_uri' => $url->redirect_uri,
    ]))->assertForbidden();
})->with(['company', 'user', 'system', 'role']);
