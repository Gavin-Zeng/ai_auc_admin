<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationUrl;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Tenant::query()->create(['name' => '演示公司', 'status' => true]);
        $application = Application::query()->create([
            'name' => '海外运营系统',
            'client_id' => 'overseas-ops',
            'client_secret' => Hash::make('overseas-secret'),
            'status' => true,
        ]);
        ApplicationUrl::query()->create([
            'application_id' => $application->id,
            'base_url' => config('app.url').'/demo-subsystem/dashboard',
            'redirect_uri' => config('app.url').'/demo-subsystem/sso/callback',
            'is_default' => true,
            'status' => true,
        ]);
        $company->applications()->attach($application);

        $directory = Menu::query()->create([
            'application_id' => $application->id,
            'name' => '运营中心',
            'path' => '/operations',
            'sort_order' => 10,
            'status' => true,
        ]);
        $dashboard = Menu::query()->create([
            'application_id' => $application->id,
            'parent_id' => $directory->id,
            'name' => '运营看板',
            'path' => '/dashboard',
            'sort_order' => 10,
            'status' => true,
        ]);
        $role = Role::query()->create(['tenant_id' => $company->id, 'name' => '运营人员', 'status' => true]);
        $role->menus()->attach([$directory->id, $dashboard->id]);

        User::query()->create([
            'name' => '平台管理员',
            'account' => 'testadmin',
            'password' => 'password',
            'is_platform_admin' => true,
            'status' => true,
        ]);
        User::query()->create([
            'tenant_id' => $company->id,
            'name' => '公司管理员',
            'account' => 'companyadmin',
            'password' => 'password',
            'is_company_admin' => true,
            'status' => true,
        ]);
        User::query()->create([
            'tenant_id' => $company->id,
            'role_id' => $role->id,
            'name' => '运营用户',
            'account' => 'operator',
            'password' => 'password',
            'status' => true,
        ]);
    }
}
