<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->post('/register', ['middleware' => 'throttle:register', 'uses' => 'Api\AuthController@register']);
$router->post('/login', ['middleware' => 'throttle:login', 'uses' => 'Api\AuthController@login']);
$router->post('/password/forgot', ['middleware' => 'throttle:password_forgot', 'uses' => 'Api\PasswordResetController@forgot']);
$router->post('/password/reset', ['middleware' => 'throttle:password_reset', 'uses' => 'Api\PasswordResetController@reset']);

$router->get('/users', 'Api\UserController@index');
$router->get('/users/{id:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}', 'Api\UserController@show');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->post('/logout', 'Api\AuthController@logout');
    $router->post('/token/refresh', 'Api\AuthController@refresh');
    $router->get('/me', 'Api\AuthController@me');
    $router->put('/me', 'Api\ProfileController@update');
    $router->post('/me/avatar', 'Api\ProfileController@updateAvatar');
    $router->put('/me/password', 'Api\ProfileController@updatePassword');
    $router->put('/me/genres', 'Api\ProfileController@updateGenres');

    $router->get('/users/search', 'Api\UserController@search');
});
