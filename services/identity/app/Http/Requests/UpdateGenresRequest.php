<?php

namespace App\Http\Requests;

class UpdateGenresRequest
{
    public const MAX_GENRES = 20;

    public static function rules(): array
    {
        return [
            'genre_ids' => ['present', 'array', 'max:'.self::MAX_GENRES],
            'genre_ids.*' => ['string', 'uuid', 'distinct:ignore_case'],
        ];
    }
}
