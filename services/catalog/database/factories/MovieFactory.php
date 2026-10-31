<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovieFactory extends Factory
{
    protected $model = Movie::class;

    public function definition(): array
    {
        return [
            'tmdb_id' => $this->faker->unique()->numberBetween(1000000000, 1999999999),
            'title' => $this->faker->sentence(3),
            'overview' => $this->faker->paragraph(),
            'poster_path' => '/'.$this->faker->lexify('??????????').'.jpg',
            'release_date' => $this->faker->date(),
            'runtime' => $this->faker->numberBetween(80, 180),
            'vote_average' => $this->faker->randomFloat(1, 1, 10),
            'popularity' => $this->faker->randomFloat(3, 0, 500),
        ];
    }

    public function local(): static
    {
        return $this->state(['tmdb_id' => null, 'is_edited' => true]);
    }
}
