<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->get('/watchlist', 'Api\WatchlistController@index');
    $router->get('/watchlist/contains', 'Api\WatchlistController@contains');
    $router->post('/watchlist', 'Api\WatchlistController@store');
    $router->delete('/watchlist/{movieId}', 'Api\WatchlistController@destroy');

    $router->get('/history', 'Api\HistoryController@index');
    $router->get('/history/contains', 'Api\HistoryController@contains');
    $router->post('/history', 'Api\HistoryController@store');
});
