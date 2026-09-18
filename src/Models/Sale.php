<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class Sale
{
    private const SALE_TYPES = ['eggs', 'birds', 'manure', 'other'];

    public static function isValidSaleType(string $v): bool { return in_array($v, self::SALE_TYPES, true); }

    public static function findByClientUuid(string $uuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) return ['record' => $existing, 'was_created' => false];

        $pdo = Database::connect();
        $pdo->prepare(
            'INSERT INTO sales
                (farm_id, batch_client_uuid, client_uuid, sale_type, quantity, unit, weight_kg, unit_price, total_amount, buyer, date_sold, notes)
             VALUES
                (:farm_id, :batch_client_uuid, :client_uuid, :sale_type, :quantity, :unit, :weight_kg, :unit_price, :total_amount, :buyer, :date_sold, :notes)'
        )->execute([
            'farm_id' => $farmId,
            'batch_client_uuid' => $data['batch_client_uuid'] ?? null,
            'client_uuid' => $data['client_uuid'],
            'sale_type' => $data['sale_type'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? null,
            'unit_price' => $data['unit_price'],
            'total_amount' => $data['total_amount'],
            'buyer' => $data['buyer'] ?? null,
            'date_sold' => $data['date_sold'],
            'notes' => $data['notes'] ?? null,
        ]);
        return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE farm_id = :farm_id ORDER BY date_sold DESC, id DESC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
