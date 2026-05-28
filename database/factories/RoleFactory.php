<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
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
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'status' => 'active',
            'is_system' => false,
        ];
    }
}
