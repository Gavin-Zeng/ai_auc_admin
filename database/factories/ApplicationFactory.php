<?php

namespace Database\Factories;

use App\Models\Application;
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
        $clientId = 'auc_'.fake()->unique()->slug(2).'_'.Str::random(8);

        return [
            'name' => fake()->words(2, true),
            'client_id' => $clientId,
            'client_secret' => Str::random(48),
            'base_url' => 'https://'.Str::slug(fake()->unique()->words(2, true)).'.example.test',
            'redirect_uri' => 'https://'.Str::slug(fake()->unique()->words(2, true)).'.example.test/sso/callback',
            'icon' => null,
            'status' => 'active',
        ];
    }
}
