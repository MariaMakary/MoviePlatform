<?php

namespace App\Services;

use App\Integration\Events\UserGenresUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Integration\EventPublisher;

class ProfileService
{
    public function __construct(private EventPublisher $events) {}

    public function updateGenres(User $user, array $genreIds): User
    {
        DB::transaction(function () use ($user, $genreIds) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $user->genres()->delete();
            $user->genres()->createMany(array_map(fn (string $id) => ['genre_id' => strtolower($id)], array_values($genreIds)));
        });

        $user->load('genres');

        $this->events->publish(UserGenresUpdated::ROUTING_KEY, UserGenresUpdated::data($user));

        return $user;
    }
}
