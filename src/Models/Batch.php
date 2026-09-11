<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class Batch
{
    private const BREEDS = ['layers', 'broilers', 'kienyeji', 'sasso'];

    public static function isValidBreed(string $breed): bool
    {
        return in_array($breed, self::BREEDS, true);
    }

    public static function findByClientUuid(string $clientUuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM batches WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $clientUuid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Idempotent create: if this client_uuid was already synced (e.g. the
     * app retried after a flaky connection acked the first attempt but the
     * response never reached the device), return the existing row instead
     * of creating a duplicate.
     */
    public static function createIfNew(int $farmId, array $data): array
    {
        $existing = self::findByClientUuid($data['client_uuid']);
        if ($existing) {
            return ['batch' => $existing, 'was_created' => false];
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO batches (farm_id, client_uuid, breed, quantity, date_acquired, cost_per_bird, source, notes)
             VALUES (:farm_id, :client_uuid, :breed, :quantity, :date_acquired, :cost_per_bird, :source, :notes)'
        );
        $stmt->execute([
            'farm_id'       => $farmId,
            'client_uuid'   => $data['client_uuid'],
            'breed'         => $data['breed'],
            'quantity'      => $data['quantity'],
            'date_acquired' => $data['date_acquired'],
            'cost_per_bird' => $data['cost_per_bird'],
            'source'        => $data['source'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ]);

        return ['batch' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM batches WHERE farm_id = :farm_id ORDER BY date_acquired DESC, id DESC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
