<?php

use App\Models\Application;
use App\Models\ApplicationUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('company user only sees applications they can access', function () {
    [$user, $tenant, $role] = simpleCompanyUser();
    [$accessibleApplication, , $accessibleMenu] = simpleApplication($tenant);
    $role->menus()->attach($accessibleMenu);
    $inaccessibleApplication = Application::factory()->create();
    ApplicationUrl::factory()->create(['application_id' => $inaccessibleApplication->id]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('applications', 1)
        ->where('applications.0.id', $accessibleApplication->id)
        ->where('applications.0.is_available', true)
        ->where('identity.roles', fn (Collection $roles) => ! $roles->contains('platform_admin'))
    );
});

test('company user without application permissions sees an empty dashboard', function () {
    [$user] = simpleCompanyUser();
    Application::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('applications', 0)
    );
});

test('platform administrator sees every application without company context', function () {
    $applications = Application::factory()->count(2)->create();
    ApplicationUrl::factory()->create(['application_id' => $applications->first()->id]);

    $this->actingAs(User::factory()->platformAdmin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('tenant', null)
            ->has('applications', 2)
            ->where('applications', fn (Collection $dashboardApplications) => $dashboardApplications
                ->pluck('id')->sort()->values()->all() === $applications->pluck('id')->sort()->values()->all())
            ->where('identity.roles', ['platform_admin'])
        );
});

test('platform administrator with company context sees all applications and can enter opened systems', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->platformAdmin()->create(['tenant_id' => $tenant->id]);
    [$openedApplication] = simpleApplication($tenant);
    $unopenedApplication = Application::factory()->create();
    ApplicationUrl::factory()->create(['application_id' => $unopenedApplication->id]);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('applications', 2)
        ->where('applications', function (Collection $applications) use ($openedApplication, $unopenedApplication): bool {
            $applications = $applications->keyBy('id');

            return $applications[$openedApplication->id]['is_available'] === true
                && $applications[$openedApplication->id]['action_url'] !== null
                && $applications[$unopenedApplication->id]['is_available'] === false
                && $applications[$unopenedApplication->id]['action_url'] === null;
        })
        ->where('identity.roles', ['platform_admin'])
    );
});
