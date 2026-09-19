<?php
namespace ShambaTrack\Models;
use ShambaTrack\Core\Database;

class VaccinationRecord
{
    public static function findByClientUuid(string $uuid): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM vaccination_records WHERE client_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Optimistic-concurrency upsert. A vaccination's status legitimately
     * changes after its first sync (pending -> done), so unlike the
     * create-once Phase 2/3 models, updates are expected here — and with
     * updates comes the possibility of two edits racing each other.
     *
     * The client always sends the `version` it last knew about. On update:
     *   - if it matches the row's current version, apply the change and
     *     bump the version.
     *   - if it does NOT match, someone else's update already landed
     *     first. Reject with a conflict instead of silently overwriting,
     *     and hand back the row as it currently stands so the client can
     *     show the farmer both versions.
     *
     * Returns ['status' => 'created'|'updated'|'conflict', 'record' => array]
     */
    public static function upsert(int $farmId, array $data): array
    {
        $pdo = Database::connect();
        $existing = self::findByClientUuid($data['client_uuid']);

        if ($existing) {
            $clientVersion = (int) ($data['version'] ?? 1);

            if ((int) $existing['version'] !== $clientVersion) {
                // Someone else's update already applied since this client
                // last knew about this record — don't overwrite it.
                return ['status' => 'conflict', 'record' => $existing];
            }

            $pdo->prepare(
                'UPDATE vaccination_records
                 SET status = :status, completed_date = :completed_date, notes = :notes, version = version + 1
                 WHERE client_uuid = :client_uuid AND version = :expected_version'
            )->execute([
                'status' => $data['status'],
                'completed_date' => $data['completed_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'client_uuid' => $data['client_uuid'],
                'expected_version' => $clientVersion,
            ]);

            // Re-check: a concurrent request could have raced between our
            // SELECT above and this UPDATE. rowCount()-based detection
            // would be simpler, but re-reading is unambiguous either way.
            $fresh = self::findByClientUuid($data['client_uuid']);
            if ((int) $fresh['version'] !== $clientVersion + 1) {
                return ['status' => 'conflict', 'record' => $fresh];
            }
            return ['status' => 'updated', 'record' => $fresh];
        }

        $pdo->prepare(
            'INSERT INTO vaccination_records
                (farm_id, batch_client_uuid, client_uuid, disease, vaccine_name, age_days, due_date, status, completed_date, notes, version)
             VALUES
                (:farm_id, :batch_client_uuid, :client_uuid, :disease, :vaccine_name, :age_days, :due_date, :status, :completed_date, :notes, 1)'
        )->execute([
            'farm_id' => $farmId,
            'batch_client_uuid' => $data['batch_client_uuid'],
            'client_uuid' => $data['client_uuid'],
            'disease' => $data['disease'],
            'vaccine_name' => $data['vaccine_name'],
            'age_days' => $data['age_days'],
            'due_date' => $data['due_date'],
            'status' => $data['status'] ?? 'pending',
            'completed_date' => $data['completed_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        return ['status' => 'created', 'record' => self::findByClientUuid($data['client_uuid'])];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM vaccination_records WHERE farm_id = :farm_id ORDER BY due_date ASC, id ASC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
