<?php

namespace App\Http\Requests;

/** The {userId} route parameter of the follow routes. */
class FollowRequest
{
    public static function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
        ];
    }
}
