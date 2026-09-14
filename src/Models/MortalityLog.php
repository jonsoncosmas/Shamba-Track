<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class MortalityLog
{
    public static function findByClientUuid(string $uuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM mortality_logs WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) return ['record' => $existing, 'was_created' => false];

        $pdo = Database::connect();
        $pdo->prepare(
            'INSERT INTO mortality_logs (farm_id, batch_client_uuid, client_uuid, date_occurred, quantity, cause, notes)
             VALUES (:farm_id, :batch_client_uuid, :client_uuid, :date_occurred, :quantity, :cause, :notes)'
        )->execute([
            'farm_id' => $farmId,
            'batch_client_uuid' => $data['batch_client_uuid'],
            'client_uuid' => $data['client_uuid'],
            'date_occurred' => $data['date_occurred'],
            'quantity' => $data['quantity'],
            'cause' => $data['cause'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM mortality_logs WHERE farm_id = :farm_id ORDER BY date_occurred DESC, id DESC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
