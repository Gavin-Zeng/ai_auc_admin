<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'role_id' => null,
            'name' => fake()->name(),
            'account' => fake()->unique()->regexify('[A-Za-z][A-Za-z0-9]{9}'),
            'password' => static::$password ??= Hash::make('password'),
            'is_company_admin' => false,
            'is_platform_admin' => false,
            'status' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function platformAdmin(): static
    {
        return $this->state(fn () => ['is_platform_admin' => true]);
    }
}
