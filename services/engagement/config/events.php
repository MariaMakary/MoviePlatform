<?php

return [
    'queue' => 'engagement.events',

    'bindings' => [
        'user.blocked',
        'user.unblocked',
        'user.deleted',
        'movie.deleted',
        'review.analyzed',
    ],

    'handlers' => [
        'ReviewAnalyzed' => App\Integration\Handlers\ReviewAnalyzedHandler::class,
        'UserBlocked' => App\Integration\Handlers\UserBlockedHandler::class,
        'UserUnblocked' => App\Integration\Handlers\UserUnblockedHandler::class,
        'UserDeleted' => App\Integration\Handlers\UserDeletedHandler::class,
    ],
];
