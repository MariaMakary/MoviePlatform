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
    ],
];
