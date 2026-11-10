<?php

namespace Database\Factories;

use App\Models\MovieNight;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MovieNightFactory extends Factory
{
    protected $model = MovieNight::class;

    public function definition(): array
    {
        return [
            'host_id' => (string) Str::orderedUuid(),
            'title' => $this->faker->sentence(3),
            'scheduled_at' => Carbon::now()->addWeek(),
            'location' => $this->faker->city(),
        ];
    }
}
