<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;

class GenreCountsRequest
{
    public const MAX_MOVIES = 1000;

    public static function rules(): array
    {
        return [
            'movie_counts' => [
                'present',
                'array',
                'max:'.self::MAX_MOVIES,
                function (string $attribute, mixed $value, callable $fail) {
                    if (is_array($value) && collect(array_keys($value))->contains(fn ($key) => ! Str::isUuid((string) $key))) {
                        $fail('Every key of movie_counts must be a movie id (UUID).');
                    }
                },
            ],
            'movie_counts.*' => ['integer', 'min:0'],
        ];
    }
}
