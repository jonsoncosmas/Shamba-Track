<?php
require __DIR__ . '/../includes/bootstrap.php';

use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Controllers\AuthController;
use ShambaTrack\Controllers\FarmController;
use ShambaTrack\Controllers\CurrencyController;
use ShambaTrack\Controllers\BatchController;
use ShambaTrack\Controllers\InfrastructureController;
use ShambaTrack\Controllers\FeedPurchaseController;
use ShambaTrack\Controllers\FeedConsumptionController;
use ShambaTrack\Controllers\EggLogController;
use ShambaTrack\Controllers\MortalityLogController;
use ShambaTrack\Controllers\CostEntryController;

$route = trim($_GET['route'] ?? '', '/');
$method = Request::method();

$routes = [
    'POST /auth/send-otp'   => [AuthController::class, 'sendOtp'],
    'POST /auth/verify-otp' => [AuthController::class, 'verifyOtp'],
    'GET /auth/me'          => [AuthController::class, 'me'],
    'POST /auth/logout'     => [AuthController::class, 'logout'],

    'POST /farms'           => [FarmController::class, 'create'],

    'GET /currencies'       => [CurrencyController::class, 'search'],

    'POST /batches'         => [BatchController::class, 'create'],
    'GET /batches'          => [BatchController::class, 'list'],

    'POST /infrastructure'  => [InfrastructureController::class, 'create'],
    'GET /infrastructure'   => [InfrastructureController::class, 'list'],

    'POST /feed-purchases'    => [FeedPurchaseController::class, 'create'],
    'GET /feed-purchases'     => [FeedPurchaseController::class, 'list'],
    'POST /feed-consumption'  => [FeedConsumptionController::class, 'create'],
    'GET /feed-consumption'   => [FeedConsumptionController::class, 'list'],
    'POST /eggs'              => [EggLogController::class, 'create'],
    'GET /eggs'               => [EggLogController::class, 'list'],
    'POST /mortality'         => [MortalityLogController::class, 'create'],
    'GET /mortality'          => [MortalityLogController::class, 'list'],
    'POST /costs'             => [CostEntryController::class, 'create'],
    'GET /costs'              => [CostEntryController::class, 'list'],
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
