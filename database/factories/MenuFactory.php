<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Menu> */
class MenuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'path' => '/'.fake()->unique()->slug(),
            'is_visible' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'status' => true,
        ];
    }
}
