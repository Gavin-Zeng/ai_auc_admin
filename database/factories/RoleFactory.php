<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'name' => fake()->unique()->jobTitle(), 'status' => true];
    }
}
