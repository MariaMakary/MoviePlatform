<?php

return [
    'queue' => 'intelligence.events',

    'bindings' => [
        'user.genres_updated',
        'user.deleted',
        'movie.deleted',
        'rating.added',
        'review.created',
        'review.updated',
        'movie.watched',
    ],

    'handlers' => [
        'RatingAdded' => App\Integration\Handlers\RatingAddedHandler::class,
        'MovieWatched' => App\Integration\Handlers\MovieWatchedHandler::class,
        'UserGenresUpdated' => App\Integration\Handlers\UserGenresUpdatedHandler::class,
        'MovieDeleted' => App\Integration\Handlers\MovieDeletedHandler::class,
        'UserDeleted' => App\Integration\Handlers\UserDeletedHandler::class,
        'ReviewCreated' => App\Integration\Handlers\AnalyzeReviewHandler::class,
        'ReviewUpdated' => App\Integration\Handlers\AnalyzeReviewHandler::class,
    ],
];
