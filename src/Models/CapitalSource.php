<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class CapitalSource
{
    private const SOURCE_TYPES = ['loan', 'salary', 'freelance', 'savings', 'other'];

    public static function isValidSourceType(string $v): bool { return in_array($v, self::SOURCE_TYPES, true); }

    public static function findByClientUuid(string $uuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM capital_sources WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) return ['record' => $existing, 'was_created' => false];

        $pdo = Database::connect();
        $pdo->prepare(
            'INSERT INTO capital_sources (farm_id, batch_client_uuid, client_uuid, source_type, amount, interest_rate, date_received, notes)
             VALUES (:farm_id, :batch_client_uuid, :client_uuid, :source_type, :amount, :interest_rate, :date_received, :notes)'
        )->execute([
            'farm_id' => $farmId,
            'batch_client_uuid' => $data['batch_client_uuid'] ?? null,
            'client_uuid' => $data['client_uuid'],
            'source_type' => $data['source_type'],
            'amount' => $data['amount'],
            'interest_rate' => $data['interest_rate'] ?? null,
            'date_received' => $data['date_received'],
            'notes' => $data['notes'] ?? null,
        ]);
        return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM capital_sources WHERE farm_id = :farm_id ORDER BY date_received DESC, id DESC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
