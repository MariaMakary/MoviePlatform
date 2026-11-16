<?php

namespace App\Enums;

/** What a feed entry is about (activities.type). */
enum ActivityType: string
{
    case Rating = 'rating';
    case Review = 'review';
    case Watched = 'watched';
    case List = 'list';
}
