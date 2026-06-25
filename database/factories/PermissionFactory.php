<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->randomElement(['dashboard', 'users', 'roles', 'applications']);
        $action = fake()->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'application_id' => null,
            'code' => fake()->unique()->bothify($group.'.'.$action.'.###'),
            'name' => fake()->words(2, true),
            'group' => $group,
            'status' => 'active',
            'description' => null,
        ];
    }
}
