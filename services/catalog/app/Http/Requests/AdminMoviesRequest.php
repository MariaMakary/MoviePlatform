<?php

namespace App\Http\Requests;

class AdminMoviesRequest
{
    public static function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            ...MoviePageRequest::rules(),
        ];
    }
}
