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
     * Unlike other Phase 2/3 models, this is a true upsert, not just
     * create-if-new: a vaccination's status legitimately changes after its
     * first sync (pending -> done, whenever the farmer marks it, which may
     * be days or weeks after the record was created). If it already
     * exists, update the mutable fields; otherwise insert the full record.
     */
    public static function upsert(int $farmId, array $data): array
    {
        $pdo = Database::connect();
        $existing = self::findByClientUuid($data['client_uuid']);

        if ($existing) {
            $pdo->prepare(
                'UPDATE vaccination_records SET status = :status, completed_date = :completed_date, notes = :notes
                 WHERE client_uuid = :client_uuid'
            )->execute([
                'status' => $data['status'],
                'completed_date' => $data['completed_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'client_uuid' => $data['client_uuid'],
            ]);
            return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => false];
        }

        $pdo->prepare(
            'INSERT INTO vaccination_records
                (farm_id, batch_client_uuid, client_uuid, disease, vaccine_name, age_days, due_date, status, completed_date, notes)
             VALUES
                (:farm_id, :batch_client_uuid, :client_uuid, :disease, :vaccine_name, :age_days, :due_date, :status, :completed_date, :notes)'
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
        return ['record' => self::findByClientUuid($data['client_uuid']), 'was_created' => true];
    }

    public static function listByFarm(int $farmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM vaccination_records WHERE farm_id = :farm_id ORDER BY due_date ASC, id ASC');
        $stmt->execute(['farm_id' => $farmId]);
        return $stmt->fetchAll();
    }
}
