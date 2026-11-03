<?php

namespace App\Http\Requests;

class UpdateAvatarRequest
{
    public const MAX_KILOBYTES = 2048;

    public static function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:jpg,png', 'max:'.self::MAX_KILOBYTES],
        ];
    }
}
