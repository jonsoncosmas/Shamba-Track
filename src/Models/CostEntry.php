<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class CostEntry
{
    private const CATEGORIES = ['labor', 'utilities', 'medication', 'transport'];
    private const LABOR_SUBTYPES = ['allowance', 'salary'];

    public static function isValidCategory(string $v): bool { return in_array($v, self::CATEGORIES, true); }
    public static function isValidLaborSubtype(?string $v): bool { return $v === null || in_array($v, self::LABOR_SUBTYPES, true); }

    public static function findByClientUuid(string $uuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM cost_entries WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) return ['record' => $existing, 'was_created' => false];

        $pdo = Database::connect();
        $pdo->prepare(
            'INSERT INTO cost_entries (farm_id, batch_client_uuid, client_uuid, category, sub_type, label, amount, date_incurred, notes)
             VALUES (:farm_id, :batch_client_uuid, :client_uuid, :category, :sub_type, :label, :amount, :date_incurred, :notes)'
        )->execute([
            'farm_id' => $farmId,
            'batch_client_uuid' => $data['batch_client_uuid'] ?? null,
            'client_uuid' => $data['client_uuid'],
            'category' => $data['category'],
            'sub_type' => $data['sub_type'] ?? null,
            'label' => $data['label'],
            'amount' => $data['amount'],
            'date_incurred' => $data['date_incurred'],
            'notes' => $data['notes'] ?? null,
        ]);
        return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM cost_entries WHERE farm_id = :farm_id ORDER BY date_incurred DESC, id DESC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
