<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('', ['filter' => 'guest'], static function (RouteCollection $routes) {
    $routes->get('login', 'AuthController::loginForm');
    $routes->post('login', 'AuthController::login');
    $routes->get('signup', 'AuthController::signupForm');
    $routes->post('signup', 'AuthController::signup');
});

$routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);

$routes->group('tickets', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('/', 'TicketController::index');
    $routes->get('new', 'TicketController::create');
    $routes->post('/', 'TicketController::store');
    $routes->get('export', 'TicketController::export');
    $routes->post('(:segment)/status', 'TicketController::updateStatus/$1');
    $routes->post('(:segment)/meta', 'TicketController::updateMeta/$1');
    $routes->post('(:segment)/messages', 'TicketController::postMessage/$1');
    $routes->get('(:segment)', 'TicketController::show/$1');
});

$routes->get('reports', 'ReportsController::index', ['filter' => 'auth:superadmin']);

$routes->group('admin/users', ['filter' => 'auth:superadmin'], static function (RouteCollection $routes) {
    $routes->get('/', 'AdminUserController::index');
    $routes->get('new', 'AdminUserController::create');
    $routes->post('/', 'AdminUserController::store');
    $routes->get('(:num)/edit', 'AdminUserController::edit/$1');
    $routes->post('(:num)', 'AdminUserController::update/$1');
});
