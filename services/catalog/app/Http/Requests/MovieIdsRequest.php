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

    public static function parse(?string $ids): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $ids)))));
    }
}
