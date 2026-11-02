<?php

namespace App\Http\Requests;

class RatingSummaryRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['required', 'uuid'],
        ];
    }
}
