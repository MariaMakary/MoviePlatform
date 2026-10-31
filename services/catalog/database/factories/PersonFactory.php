<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'tmdb_id' => $this->faker->unique()->numberBetween(1000000000, 1999999999),
            'name' => $this->faker->name(),
            'known_for' => 'Acting',
        ];
    }
}
