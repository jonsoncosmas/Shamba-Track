<?php

namespace ShambaTrack\Core\Http;

class Request
{
    private static ?array $jsonBody = null;

    /** Parse and cache the JSON request body as an associative array. */
    public static function json(): array
    {
        if (self::$jsonBody === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            self::$jsonBody = is_array($decoded) ? $decoded : [];
        }
        return self::$jsonBody;
    }

    public static function input(string $key, $default = null)
    {
        $body = self::json();
        return $body[$key] ?? $_GET[$key] ?? $default;
    }

    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
}
