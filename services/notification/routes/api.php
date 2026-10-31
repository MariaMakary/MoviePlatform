<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->get('/notifications', 'Api\NotificationController@index');
    $router->get('/notifications/unread-count', 'Api\NotificationController@unreadCount');
    $router->patch('/notifications/read-all', 'Api\NotificationController@markAllRead');
    $router->patch('/notifications/{id}/read', 'Api\NotificationController@markRead');
});
