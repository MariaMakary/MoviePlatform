<?php

return [
    'queue' => 'social.events',

    'bindings' => [
        'user.blocked',
        'user.unblocked',
        'user.deleted',
        'movie.deleted',
        'rating.added',
        'review.created',
        'review.updated',
        'movie.watched',
        'list.created',
    ],

    'handlers' => [
        'MovieDeleted' => App\Integration\Handlers\MovieDeletedHandler::class,
        'RatingAdded' => App\Integration\Handlers\ActivityHandler::class,
        'ReviewCreated' => App\Integration\Handlers\ActivityHandler::class,
        'ReviewUpdated' => App\Integration\Handlers\ActivityHandler::class,
        'MovieWatched' => App\Integration\Handlers\ActivityHandler::class,
        'ListCreated' => App\Integration\Handlers\ActivityHandler::class,
    ],
];
