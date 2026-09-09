<?php

namespace ShambaTrack\Models;

use ShambaTrack\Core\Database;

class Farm
{
    public static function findByOwner(int $userId): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM farms WHERE owner_user_id = :uid ORDER BY id ASC LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM farms WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $ownerUserId, array $data): array
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            'INSERT INTO farms (owner_user_id, name, region, district, village, currency_code)
             VALUES (:owner_user_id, :name, :region, :district, :village, :currency_code)'
        );
        $stmt->execute([
            'owner_user_id' => $ownerUserId,
            'name'          => $data['name'],
            'region'        => $data['region'] ?? null,
            'district'      => $data['district'] ?? null,
            'village'       => $data['village'] ?? null,
            'currency_code' => $data['currency_code'],
        ]);

        $farmId = (int) $pdo->lastInsertId();

        // Also register the owner in farm_users so the multi-tenant join
        // table is populated from day one (Phase 0 design decision).
        $pdo->prepare(
            'INSERT INTO farm_users (farm_id, user_id, role) VALUES (:farm_id, :user_id, "owner")'
        )->execute(['farm_id' => $farmId, 'user_id' => $ownerUserId]);

        return self::findById($farmId);
    }
}
