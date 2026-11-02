<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->get('/movies/{id}/rating-summary', 'Api\RatingController@summary');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->post('/ratings', 'Api\RatingController@store');

    $router->get('/watchlist', 'Api\WatchlistController@index');
    $router->get('/watchlist/contains', 'Api\WatchlistController@contains');
    $router->post('/watchlist', 'Api\WatchlistController@store');
    $router->delete('/watchlist/{movieId}', 'Api\WatchlistController@destroy');

    $router->get('/history', 'Api\HistoryController@index');
    $router->get('/history/contains', 'Api\HistoryController@contains');
    $router->post('/history', 'Api\HistoryController@store');
});
