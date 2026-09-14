<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class FeedConsumption
{
    public static function findByClientUuid(string $uuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM feed_consumption WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) return ['record' => $existing, 'was_created' => false];

        $pdo = Database::connect();
        $pdo->prepare(
            'INSERT INTO feed_consumption (farm_id, batch_client_uuid, client_uuid, quantity_kg, date_consumed, notes)
             VALUES (:farm_id, :batch_client_uuid, :client_uuid, :quantity_kg, :date_consumed, :notes)'
        )->execute([
            'farm_id' => $farmId,
            'batch_client_uuid' => $data['batch_client_uuid'],
            'client_uuid' => $data['client_uuid'],
            'quantity_kg' => $data['quantity_kg'],
            'date_consumed' => $data['date_consumed'],
            'notes' => $data['notes'] ?? null,
        ]);
        return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'SELECT fc.* FROM feed_consumption fc WHERE fc.farm_id = :farm_id ORDER BY fc.date_consumed DESC, fc.id DESC'
        );
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
