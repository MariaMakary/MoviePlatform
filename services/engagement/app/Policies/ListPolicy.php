<?php

namespace App\Policies;

use App\Models\MovieList;
use MoviePlatform\Common\Auth\JwtUser;

class ListPolicy
{
    public function update(JwtUser $user, MovieList $list): bool
    {
        return $list->user_id === $user->id;
    }

    public function delete(JwtUser $user, MovieList $list): bool
    {
        return $list->user_id === $user->id;
    }
}
