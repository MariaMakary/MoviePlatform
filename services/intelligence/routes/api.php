<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->post('/calendar/ics', 'Api\CalendarController@ics');
$router->get('/calendar/google-link', 'Api\CalendarController@googleLink');
