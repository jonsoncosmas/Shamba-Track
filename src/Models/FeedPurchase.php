<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class FeedPurchase
{
    private const SOURCES = ['bought', 'home_made'];
    private const FEED_TYPES = ['starter', 'grower', 'layers_mash', 'other'];

    public static function isValidSource(string $v): bool { return in_array($v, self::SOURCES, true); }
    public static function isValidFeedType(string $v): bool { return in_array($v, self::FEED_TYPES, true); }

    public static function findByClientUuid(string $uuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM feed_purchases WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) return ['record' => $existing, 'was_created' => false];

        $pdo = Database::connect();
        $pdo->prepare(
            'INSERT INTO feed_purchases (farm_id, batch_client_uuid, client_uuid, source, feed_type, quantity_kg, total_cost, date_purchased, notes)
             VALUES (:farm_id, :batch_client_uuid, :client_uuid, :source, :feed_type, :quantity_kg, :total_cost, :date_purchased, :notes)'
        )->execute([
            'farm_id' => $farmId,
            'batch_client_uuid' => $data['batch_client_uuid'] ?? null,
            'client_uuid' => $data['client_uuid'],
            'source' => $data['source'],
            'feed_type' => $data['feed_type'],
            'quantity_kg' => $data['quantity_kg'],
            'total_cost' => $data['total_cost'],
            'date_purchased' => $data['date_purchased'],
            'notes' => $data['notes'] ?? null,
        ]);
        return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM feed_purchases WHERE farm_id = :farm_id ORDER BY date_purchased DESC, id DESC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
