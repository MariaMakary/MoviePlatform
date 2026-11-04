<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->post('/calendar/ics', 'Api\CalendarController@ics');
$router->get('/calendar/google-link', 'Api\CalendarController@googleLink');

// Manual testing only, so nobody else can spend the Hugging Face token.
$router->group(['middleware' => ['jwt', 'admin']], function ($router) {
    $router->post('/sentiment/analyze', 'Api\SentimentController@analyze');
});
