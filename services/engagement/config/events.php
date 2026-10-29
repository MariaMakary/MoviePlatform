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
    ],
];
