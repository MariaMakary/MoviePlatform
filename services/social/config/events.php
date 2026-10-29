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
    ],
];
