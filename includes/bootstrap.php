<?php

/**
 * Shamba Track — App Bootstrap
 * Included at the top of every entry point (index.php, api/*.php).
 */

// Config (git-ignored — copy config.example.php to config.php first)
$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    die('Missing config/config.php — copy config/config.example.php and fill in your values.');
}
require $configPath;

require __DIR__ . '/../src/Core/Autoloader.php';

// Basic security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Session (used for the auto-login token check, not for storing secrets)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * SESSION_TOKEN_TTL_DAYS,
        'path'     => '/',
        'secure'   => APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
