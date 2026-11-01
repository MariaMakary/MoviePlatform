<?php

namespace App\Http\Requests;

class WatchlistMovieRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['required', 'uuid'],
        ];
    }
}
