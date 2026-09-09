<?php

namespace ShambaTrack\Models;

use PDO;
use ShambaTrack\Core\Database;

class User
{
    public static function findByPhone(string $phoneNumber): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE phone_number = :phone LIMIT 1');
        $stmt->execute(['phone' => $phoneNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $phoneNumber, string $preferredLanguage = 'sw'): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO users (phone_number, preferred_language) VALUES (:phone, :lang)'
        );
        $stmt->execute(['phone' => $phoneNumber, 'lang' => $preferredLanguage]);
        return self::findById((int) $pdo->lastInsertId());
    }

    /** Find existing user by phone, or create one. Returns [user, wasCreated]. */
    public static function findOrCreate(string $phoneNumber): array
    {
        $existing = self::findByPhone($phoneNumber);
        if ($existing) {
            return [$existing, false];
        }
        return [self::create($phoneNumber), true];
    }
}
