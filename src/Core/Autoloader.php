<?php

/**
 * Minimal PSR-4-style autoloader for the ShambaTrack\ namespace.
 * Kept dependency-free so the app runs without requiring Composer
 * on shared hosting where `composer install` may not be available.
 *
 * ShambaTrack\Core\Database  ->  src/Core/Database.php
 * ShambaTrack\Models\Farm    ->  src/Models/Farm.php
 */
spl_autoload_register(function (string $class) {
    $prefix = 'ShambaTrack\\';
    $baseDir = __DIR__ . '/../';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
