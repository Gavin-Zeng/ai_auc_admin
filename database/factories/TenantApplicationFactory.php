<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Tenant;
use App\Models\TenantApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantApplication>
 */
class TenantApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'application_id' => Application::factory(),
            'required_permissions' => [],
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
