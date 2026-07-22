<?php

use App\Models\Application;
use App\Models\ApplicationUrl;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function simpleCompanyUser(bool $admin = false): array
{
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $admin ? null : $role->id,
        'is_company_admin' => $admin,
    ]);

    return [$user, $tenant, $role];
}

function simpleApplication(Tenant $tenant): array
{
    $application = Application::factory()->create();
    $url = ApplicationUrl::factory()->create(['application_id' => $application->id]);
    $tenant->applications()->attach($application);
    $menu = Menu::factory()->create(['application_id' => $application->id]);

    return [$application, $url, $menu];
}
