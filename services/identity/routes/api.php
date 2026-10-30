<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->post('/register', 'Api\AuthController@register');
$router->post('/login', 'Api\AuthController@login');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->post('/logout', 'Api\AuthController@logout');
    $router->post('/token/refresh', 'Api\AuthController@refresh');
    $router->get('/me', 'Api\AuthController@me');
});
