<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

$router->get('/movies/{id}/rating-summary', 'Api\RatingController@summary');

$router->get("/lists/{id:$uuid}", 'Api\ListController@show');
$router->get("/users/{id:$uuid}/lists", 'Api\ListController@ofUser');

$router->group(['middleware' => 'jwt'], function ($router) use ($uuid) {
    $router->post('/ratings', 'Api\RatingController@store');

    $router->get('/lists', 'Api\ListController@index');
    $router->post('/lists', 'Api\ListController@store');
    $router->put("/lists/{id:$uuid}", 'Api\ListController@update');
    $router->delete("/lists/{id:$uuid}", 'Api\ListController@destroy');

    $router->get('/watchlist', 'Api\WatchlistController@index');
    $router->get('/watchlist/contains', 'Api\WatchlistController@contains');
    $router->post('/watchlist', 'Api\WatchlistController@store');
    $router->delete('/watchlist/{movieId}', 'Api\WatchlistController@destroy');

    $router->get('/history', 'Api\HistoryController@index');
    $router->get('/history/contains', 'Api\HistoryController@contains');
    $router->post('/history', 'Api\HistoryController@store');
});
