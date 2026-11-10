<?php

namespace MoviePlatform\Common\Notifications;

enum NotificationType: string
{
    case Welcome = 'welcome';
    case NewFollower = 'new_follower';
    case MovieNightInvite = 'movie_night_invite';
    case MovieNightInviteAnswered = 'movie_night_invite_answered';
    case MovieNightFinalized = 'movie_night_finalized';
}
