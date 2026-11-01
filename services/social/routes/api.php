<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->get('/users/{id}/followers', 'Api\FollowController@followers');
$router->get('/users/{id}/following', 'Api\FollowController@following');
$router->get('/users/{id}/follow-stats', 'Api\FollowController@stats');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->post('/follow/{userId}', 'Api\FollowController@store');
    $router->delete('/follow/{userId}', 'Api\FollowController@destroy');
});
