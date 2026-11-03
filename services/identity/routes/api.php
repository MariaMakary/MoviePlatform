<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->post('/register', 'Api\AuthController@register');
$router->post('/login', 'Api\AuthController@login');
$router->post('/password/forgot', 'Api\PasswordResetController@forgot');
$router->post('/password/reset', 'Api\PasswordResetController@reset');

$router->get('/users', 'Api\UserController@index');
$router->get('/users/{id:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}', 'Api\UserController@show');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->post('/logout', 'Api\AuthController@logout');
    $router->post('/token/refresh', 'Api\AuthController@refresh');
    $router->get('/me', 'Api\AuthController@me');

    $router->get('/users/search', 'Api\UserController@search');
});
