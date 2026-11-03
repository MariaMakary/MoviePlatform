<?php

namespace App\Http\Requests;

class UserIdsRequest
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

        return array_values(array_unique(array_filter(array_map(fn (string $id) => strtolower(trim($id)), explode(',', $ids)))));
    }
}
