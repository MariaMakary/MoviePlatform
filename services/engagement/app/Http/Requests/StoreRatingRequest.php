<?php

namespace App\Http\Requests;

class StoreRatingRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['required', 'uuid'],
            'score' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
