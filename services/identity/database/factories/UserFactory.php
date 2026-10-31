<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use MoviePlatform\Common\Auth\Role;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'password',
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function blocked(): static
    {
        return $this->state(['is_blocked' => true]);
    }
}
