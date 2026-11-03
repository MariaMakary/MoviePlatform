<?php

namespace App\Http\Requests;

class InviteRequest
{
    public const MAX_USERS = 20;

    public static function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'max:'.self::MAX_USERS],
            'user_ids.*' => ['string', 'uuid', 'distinct:ignore_case'],
        ];
    }
}
