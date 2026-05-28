<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
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
            'code' => $code,
            'name' => fake()->words(2, true),
            'client_id' => 'auc_'.$code.'_'.Str::random(8),
            'client_secret' => Str::random(48),
            'base_url' => 'https://'.$code.'.example.test',
            'redirect_uri' => 'https://'.$code.'.example.test/sso/callback',
            'icon' => null,
            'required_permissions' => [],
            'status' => 'active',
        ];
    }
}
