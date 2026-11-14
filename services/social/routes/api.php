<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

$router->get('/users/{id}/followers', 'Api\FollowController@followers');
$router->get('/users/{id}/following', 'Api\FollowController@following');
$router->get('/users/{id}/follow-stats', 'Api\FollowController@stats');
$router->get('/users/{id}/privacy', 'Api\PrivacyController@showFor');

$router->group(['middleware' => 'jwt'], function ($router) use ($uuid) {
    $router->post('/follow/{userId}', 'Api\FollowController@store');
    $router->delete('/follow/{userId}', 'Api\FollowController@destroy');
    $router->get('/follow/status', 'Api\FollowController@status');

    $router->get('/privacy', 'Api\PrivacyController@show');
    $router->put('/privacy', 'Api\PrivacyController@update');

    $router->post('/movie-nights', 'Api\MovieNightController@store');
    $router->get('/movie-nights', 'Api\MovieNightController@index');
    $router->get("/movie-nights/{id:$uuid}", 'Api\MovieNightController@show');
    $router->post("/movie-nights/{id:$uuid}/invites", 'Api\ParticipantController@invite');
    $router->patch("/movie-nights/{id:$uuid}/rsvp", 'Api\ParticipantController@rsvp');
});
