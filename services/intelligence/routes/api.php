<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->post('/calendar/ics', 'Api\CalendarController@ics');
$router->get('/calendar/google-link', 'Api\CalendarController@googleLink');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->get('/recommendations/{userId:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}', 'Api\RecommendationController@show');
});

$router->group(['middleware' => ['jwt', 'admin']], function ($router) {
    $router->post('/sentiment/analyze', 'Api\SentimentController@analyze');
});
