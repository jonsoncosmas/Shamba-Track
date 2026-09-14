<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class EggLog
{
    public static function findByClientUuid(string $uuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM egg_logs WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) return ['record' => $existing, 'was_created' => false];

        $pdo = Database::connect();
        $pdo->prepare(
            'INSERT INTO egg_logs (farm_id, batch_client_uuid, client_uuid, date_collected, quantity_whole, quantity_broken, notes)
             VALUES (:farm_id, :batch_client_uuid, :client_uuid, :date_collected, :quantity_whole, :quantity_broken, :notes)'
        )->execute([
            'farm_id' => $farmId,
            'batch_client_uuid' => $data['batch_client_uuid'],
            'client_uuid' => $data['client_uuid'],
            'date_collected' => $data['date_collected'],
            'quantity_whole' => $data['quantity_whole'],
            'quantity_broken' => $data['quantity_broken'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);
        return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM egg_logs WHERE farm_id = :farm_id ORDER BY date_collected DESC, id DESC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
