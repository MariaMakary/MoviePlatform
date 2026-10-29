<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

class GenreFactory extends Factory
{
    protected $model = Genre::class;

    public function definition(): array
    {
        return [
            'tmdb_id' => $this->faker->unique()->numberBetween(1, 99999),
            'name' => $this->faker->unique()->word().' '.$this->faker->unique()->numberBetween(1, 99999),
        ];
    }
}
