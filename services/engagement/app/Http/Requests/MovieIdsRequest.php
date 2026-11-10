<?php

namespace App\Http\Requests;

class MovieIdsRequest
{
    public const MAX_IDS = 100;

    public static function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['uuid'],
        ];
    }

    public static function parse(mixed $ids): array
    {
        if (! is_string($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $ids)))));
    }
}
