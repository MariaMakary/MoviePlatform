<?php

return [
    'queue' => 'notification.events',

    'bindings' => [
        'user.registered',
        'user.deleted',
        'user.followed',
        'movie_night.invite_sent',
        'movie_night.invite_answered',
        'movie_night.finalized',
    ],

    'handlers' => [
        'UserRegistered' => App\Integration\Handlers\UserRegisteredHandler::class,
        'UserFollowed' => App\Integration\Handlers\UserFollowedHandler::class,
        'MovieNightInviteSent' => App\Integration\Handlers\MovieNightInviteSentHandler::class,
        'MovieNightInviteAnswered' => App\Integration\Handlers\MovieNightInviteAnsweredHandler::class,
        'MovieNightFinalized' => App\Integration\Handlers\MovieNightFinalizedHandler::class,
    ],
];
