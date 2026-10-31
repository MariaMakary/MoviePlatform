<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->get('/movies', 'Api\MovieController@index');
