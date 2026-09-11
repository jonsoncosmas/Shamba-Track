<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class InfrastructureItem
{
    private const CATEGORIES = ['coop', 'land', 'equipment'];
    private const LAND_STATUSES = ['bought', 'rented'];

    public static function isValidCategory(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }

    public static function isValidLandStatus(?string $status): bool
    {
        return $status === null || in_array($status, self::LAND_STATUSES, true);
    }

    public static function findByClientUuid(string $clientUuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM infrastructure_items WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $clientUuid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Idempotent create — same reasoning as Batch::createIfNew(). */
    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) {
            return ['item' => $existing, 'was_created' => false];
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO infrastructure_items
                (farm_id, batch_id, client_uuid, category, item_name, land_status, amount, date_incurred, notes)
             VALUES
                (:farm_id, :batch_id, :client_uuid, :category, :item_name, :land_status, :amount, :date_incurred, :notes)'
        );
        $stmt->execute([
            'farm_id'       => $farmId,
            'batch_id'      => $data['batch_id'] ?? null,
            'client_uuid'   => $data['client_uuid'],
            'category'      => $data['category'],
            'item_name'     => $data['item_name'],
            'land_status'   => $data['land_status'] ?? null,
            'amount'        => $data['amount'],
            'date_incurred' => $data['date_incurred'],
            'notes'         => $data['notes'] ?? null,
        ]);

        return ['item' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM infrastructure_items WHERE farm_id = :farm_id ORDER BY date_incurred DESC, id DESC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
