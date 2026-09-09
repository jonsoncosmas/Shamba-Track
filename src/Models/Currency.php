<?php

namespace ShambaTrack\Models;

use ShambaTrack\Core\Database;

class Currency
{
    /** Search active currencies by code, name, or country — for the searchable picker. */
    public static function search(string $query = '', int $limit = 20): array
    {
        $pdo = Database::connect();

        if ($query === '') {
            $stmt = $pdo->prepare(
                'SELECT code, name, symbol, country FROM currencies
                 WHERE is_active = 1 ORDER BY name ASC LIMIT :limit'
            );
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $pdo->prepare(
            'SELECT code, name, symbol, country FROM currencies
             WHERE is_active = 1
               AND (code LIKE :q1 OR name LIKE :q2 OR country LIKE :q3)
             ORDER BY
                CASE WHEN code = :exact THEN 0 ELSE 1 END,
                name ASC
             LIMIT :limit'
        );
        $likeValue = '%' . $query . '%';
        $stmt->bindValue('q1', $likeValue);
        $stmt->bindValue('q2', $likeValue);
        $stmt->bindValue('q3', $likeValue);
        $stmt->bindValue('exact', strtoupper($query));
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function exists(string $code): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT 1 FROM currencies WHERE code = :code AND is_active = 1');
        $stmt->execute(['code' => $code]);
        return (bool) $stmt->fetchColumn();
    }
}
