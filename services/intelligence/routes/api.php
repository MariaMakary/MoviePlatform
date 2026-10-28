<?php

$router->get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'intelligence',
]));