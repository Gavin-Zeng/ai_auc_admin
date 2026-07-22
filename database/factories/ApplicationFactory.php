<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<Application> */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'client_id' => (string) Str::uuid(),
            'client_secret' => Hash::make(Str::random(48)),
            'status' => true,
        ];
    }
}
