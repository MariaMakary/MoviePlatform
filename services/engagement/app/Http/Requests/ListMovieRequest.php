<?php

namespace App\Http\Requests;

class ListMovieRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['required', 'uuid'],
        ];
    }
}
