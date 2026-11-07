<?php

namespace App\Http\Requests;

/** A user id route parameter ({userId} / {id}) of the follow and privacy routes. */
class FollowRequest
{
    public static function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
        ];
    }
}
