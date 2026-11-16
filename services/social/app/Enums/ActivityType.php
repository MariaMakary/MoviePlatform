<?php

namespace App\Enums;

/** What a feed entry is about (activities.type). */
enum ActivityType: string
{
    case Rating = 'rating';
    case Review = 'review';
    case Watched = 'watched';
    case List = 'list';

    /** The privacy setting (privacy_settings column) that shows or hides this type to other users. */
    public function privacyField(): string
    {
        return match ($this) {
            self::Rating => 'show_ratings',
            self::Review => 'show_reviews',
            self::Watched => 'show_history',
            self::List => 'show_lists',
        };
    }
}
