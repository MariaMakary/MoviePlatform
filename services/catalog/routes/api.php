<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->get('/movies', 'Api\MovieController@index');
$router->get('/movies/popular', 'Api\MovieController@popular');
$router->get('/movies/upcoming', 'Api\MovieController@upcoming');
