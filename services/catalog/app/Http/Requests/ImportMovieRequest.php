<?php

namespace App\Http\Requests;

class ImportMovieRequest
{
    public static function rules(): array
    {
        return [
            'tmdb_id' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
