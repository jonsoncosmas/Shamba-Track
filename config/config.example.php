<?php
/**
 * Shamba Track — Configuration Template
 *
 * Copy this file to config.php and fill in real values.
 * config.php is git-ignored — never commit real credentials.
 */

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'shamba_track');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// --- App ---
define('APP_ENV', 'development'); // 'development' | 'production'
define('APP_URL', 'https://app.example.com'); // subdomain root, no trailing slash
define('APP_NAME', 'Shamba Track');
define('APP_TIMEZONE', 'Africa/Dar_es_Salaam');

// --- Auth / Session ---
define('SESSION_TOKEN_TTL_DAYS', 90);   // how long an auto-login session token stays valid
define('OTP_TTL_MINUTES', 5);           // how long an OTP code stays valid
define('OTP_LENGTH', 6);

// --- SMS Gateway (for OTP) ---
// Phase 1 will start with a stub/test provider; fill in real credentials
// once an SMS gateway (e.g. a Tanzanian aggregator) is chosen.
define('SMS_PROVIDER', 'stub'); // 'stub' | 'africastalking' | etc.
define('SMS_API_KEY', '');
define('SMS_SENDER_ID', 'ShambaTrack');

// --- Error reporting ---
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

date_default_timezone_set(APP_TIMEZONE);
