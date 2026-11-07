<?php

namespace App\Http\Requests;

class GenreMoviesRequest
{
    /** Always descending; the first one is the default. */
    public const SORTS = ['popularity', 'vote_average'];

    public static function rules(): array
    {
        return [
            'sort' => ['sometimes', 'in:'.implode(',', self::SORTS)],
            ...MoviePageRequest::rules(),
        ];
    }
}
