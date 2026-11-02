<?php

namespace App\Http\Requests;

class ListIndexRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['sometimes', 'uuid'],
        ];
    }
}
