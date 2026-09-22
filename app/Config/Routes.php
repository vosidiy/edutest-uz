<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('q/(:segment)', 'PublicSite\QuizController::show/$1', ['as' => 'public-quiz']);
$routes->get('media/(:segment)', 'PublicSite\MediaController::show/$1', ['as' => 'signed-media']);

$routes->group('', ['namespace' => 'App\Controllers\Teacher', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index', ['as' => 'dashboard']);
    $routes->get('quizzes', 'QuizController::index', ['as' => 'quizzes']);
    $routes->get('quizzes/archived', 'QuizController::archived', ['as' => 'quizzes-archived']);
    $routes->get('quizzes/trash', 'QuizController::trash', ['as' => 'quizzes-trash']);
    $routes->get('quizzes/(:segment)/edit', 'QuizController::edit/$1', ['as' => 'quiz-edit']);
});

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('csrf', 'CsrfController::show');
    $routes->post('quizzes', 'QuizController::create');
    $routes->get('quizzes/(:segment)', 'QuizController::show/$1');
    $routes->put('quizzes/(:segment)', 'QuizController::update/$1');
    foreach (['publish', 'close', 'reopen', 'archive', 'unarchive', 'trash', 'restore'] as $action) {
        $routes->post("quizzes/(:segment)/{$action}", "QuizController::transition/$1/{$action}");
    }
    $routes->post('quizzes/(:segment)/duplicate', 'QuizController::duplicate/$1');
    $routes->post('quizzes/(:segment)/questions/(:num)/media', 'MediaController::putQuestion/$1/$2');
    $routes->delete('quizzes/(:segment)/questions/(:num)/media', 'MediaController::deleteQuestion/$1/$2');
    $routes->post('quizzes/(:segment)/options/(:num)/media', 'MediaController::putOption/$1/$2');
    $routes->delete('quizzes/(:segment)/options/(:num)/media', 'MediaController::deleteOption/$1/$2');
});

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
