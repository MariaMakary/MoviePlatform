<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * What a user likes, built from their events (task 1244):
 * genre_weights / people_weights {catalog id: weight}, seen_movie_ids {movie_id: last score, or null if only watched},
 * favorite_genre_ids [catalog genre id], replaced as a whole by UserGenresUpdated.
 */
class UserTasteProfile extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'genre_weights', 'people_weights', 'seen_movie_ids', 'favorite_genre_ids'];

    protected $casts = [
        'genre_weights' => 'array',
        'people_weights' => 'array',
        'seen_movie_ids' => 'array',
        'favorite_genre_ids' => 'array',
    ];

    protected $attributes = [
        'genre_weights' => '{}',
        'people_weights' => '{}',
        'seen_movie_ids' => '{}',
        'favorite_genre_ids' => '[]',
    ];
}
