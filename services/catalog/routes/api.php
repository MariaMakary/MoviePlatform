<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->get('/movies', 'Api\MovieController@index');
$router->get('/movies/popular', 'Api\MovieController@popular');
$router->get('/movies/upcoming', 'Api\MovieController@upcoming');
$router->get('/movies/{id:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}', 'Api\MovieController@show');
