<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('', ['namespace' => 'App\Controllers\Auth'], static function (RouteCollection $routes): void {
    $routes->get('register', 'RegisterController::index', ['as' => 'register']);
    $routes->post('register', 'RegisterController::create');
    $routes->get('login', 'LoginController::index', ['as' => 'login']);
    $routes->post('login', 'LoginController::authenticate');
    $routes->post('logout', 'LoginController::logout', [
        'as'     => 'logout',
        'filter' => 'auth',
    ]);
});
