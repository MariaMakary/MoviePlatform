<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->get('/suggestions/weather', 'Api\SuggestionController@weather');

$router->post('/calendar/ics', 'Api\CalendarController@ics');
$router->get('/calendar/google-link', 'Api\CalendarController@googleLink');

$router->group(['middleware' => ['jwt', 'admin']], function ($router) {
    $router->post('/sentiment/analyze', 'Api\SentimentController@analyze');
});
