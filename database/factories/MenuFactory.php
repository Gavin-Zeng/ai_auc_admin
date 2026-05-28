<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->slug(2);

        return [
            'tenant_id' => Tenant::factory(),
            'menu_group_id' => null,
            'application_id' => null,
            'parent_id' => null,
            'code' => $code,
            'title' => fake()->words(2, true),
            'href' => '/'.$code,
            'icon' => null,
            'required_permissions' => [],
            'sort_order' => fake()->numberBetween(1, 100),
            'is_visible' => true,
            'status' => 'active',
        ];
    }
}
