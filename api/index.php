<?php

require __DIR__ . '/../includes/bootstrap.php';

use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Controllers\AuthController;
use ShambaTrack\Controllers\FarmController;
use ShambaTrack\Controllers\CurrencyController;

$route = trim($_GET['route'] ?? '', '/');
$method = Request::method();

// [METHOD, route] => [Controller, method]
$routes = [
    'POST /auth/send-otp'   => [AuthController::class, 'sendOtp'],
    'POST /auth/verify-otp' => [AuthController::class, 'verifyOtp'],
    'GET /auth/me'          => [AuthController::class, 'me'],
    'POST /auth/logout'     => [AuthController::class, 'logout'],

    'POST /farms'           => [FarmController::class, 'create'],

    'GET /currencies'       => [CurrencyController::class, 'search'],
];

$key = $method . ' /' . $route;

if (!isset($routes[$key])) {
    Response::error('Not found', 404, ['route' => $route, 'method' => $method]);
}

try {
    [$controller, $action] = $routes[$key];
    $controller::$action();
} catch (\Throwable $e) {
    error_log('[API] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error(
        APP_ENV === 'development' ? $e->getMessage() : 'Server error. Please try again.',
        500
    );
}
