<?php

namespace App\Http\Requests;

class StoreReviewRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['required', 'uuid'],
            ...UpdateReviewRequest::rules(),
        ];
    }
}
