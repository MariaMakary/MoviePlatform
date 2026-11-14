<?php

namespace App\Http\Requests;

/** GET /follow/status?ids=a,b,c: the comma-separated ids, split into an array before validating. */
class FollowStatusRequest
{
    public const MAX_IDS = 100;

    public static function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['string', 'uuid'],
        ];
    }
}
