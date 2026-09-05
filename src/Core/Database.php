<?php

namespace ShambaTrack\Core;

use PDO;
use PDOException;

/**
 * Single shared PDO connection.
 * Usage: $pdo = Database::connect();
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Never leak connection details to the client
                error_log('DB connection failed: ' . $e->getMessage());
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed']));
            }
        }

        return self::$instance;
    }
}
