<?php

namespace App\Policies;

use App\Models\Review;
use MoviePlatform\Common\Auth\JwtUser;

class ReviewPolicy
{
    public function update(JwtUser $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(JwtUser $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }
}
