<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationUrl;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApplicationUrl> */
class ApplicationUrlFactory extends Factory
{
    public function definition(): array
    {
        $host = fake()->unique()->domainName();

        return [
            'application_id' => Application::factory(),
            'base_url' => "https://{$host}",
            'redirect_uri' => "https://{$host}/sso/callback",
            'is_default' => true,
            'status' => true,
        ];
    }
}
