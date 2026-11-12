<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->get('/movies', 'Api\MovieController@index');
$router->get('/movies/popular', 'Api\MovieController@popular');
$router->get('/movies/upcoming', 'Api\MovieController@upcoming');
$router->get('/movies/search', 'Api\MovieController@search');
$router->get('/movies/{id:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}', 'Api\MovieController@show');

$router->group(['middleware' => 'service', 'prefix' => 'internal'], function ($router) {
    $router->get('/discover', 'Api\InternalMovieController@discover');
});

$router->get('/genres', 'Api\GenreController@index');
$router->get('/genres/{id:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}/movies', 'Api\GenreController@movies');

$router->get('/people/{id:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}', 'Api\PersonController@show');
