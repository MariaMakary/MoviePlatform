<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->post('/register', ['middleware' => 'throttle:register', 'uses' => 'Api\AuthController@register']);
$router->post('/login', ['middleware' => 'throttle:login', 'uses' => 'Api\AuthController@login']);
$router->post('/password/forgot', ['middleware' => 'throttle:password_forgot', 'uses' => 'Api\PasswordResetController@forgot']);
$router->post('/password/reset', ['middleware' => 'throttle:password_reset', 'uses' => 'Api\PasswordResetController@reset']);

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

$router->get('/users', 'Api\UserController@index');
$router->get("/users/{id:$uuid}", 'Api\UserController@show');

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

$router->group(['middleware' => ['jwt', 'admin']], function ($router) use ($uuid) {
    $router->get('/admin/users', 'Api\AdminUserController@index');
    $router->patch("/admin/users/{id:$uuid}/block", 'Api\AdminUserController@block');
    $router->patch("/admin/users/{id:$uuid}/unblock", 'Api\AdminUserController@unblock');
    $router->delete("/admin/users/{id:$uuid}", 'Api\AdminUserController@destroy');
$router->group(['middleware' => ['jwt', 'admin']], function ($router) {
    $router->get('/stats', 'Api\StatsController@show');
});
