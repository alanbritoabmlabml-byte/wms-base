<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => Str::lower(Str::random(8)),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'wms1234',
            'pin' => '123456',
            'is_active' => true,
        ];
    }
}
