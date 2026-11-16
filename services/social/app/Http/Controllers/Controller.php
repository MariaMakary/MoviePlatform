<?php

namespace App\Http\Controllers;

use App\Http\Requests\FollowRequest;
use Illuminate\Support\Facades\Validator;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    /** A user id from the URL: 422 on `user_id` when it is not a UUID (follow, privacy and activity routes). */
    protected function validUserId(string $userId): string
    {
        return Validator::make(['user_id' => $userId], FollowRequest::rules())->validate()['user_id'];
    }
}
