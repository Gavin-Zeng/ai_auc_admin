<?php

namespace Database\Factories;

use App\Models\MenuGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuGroup>
 */
class MenuGroupFactory extends Factory
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
            'sort_order' => fake()->numberBetween(1, 100),
            'is_visible' => true,
        ];
    }
}
