<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\VaccinationRecord;
use ShambaTrack\Models\Farm;

class VaccinationController
{
    private const STATUSES = ['pending', 'done'];

    public static function upsert(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $batchClientUuid = (string) Request::input('batch_client_uuid', '');
        $disease = trim((string) Request::input('disease', ''));
        $vaccineName = trim((string) Request::input('vaccine_name', ''));
        $ageDays = (int) Request::input('age_days', 0);
        $dueDate = (string) Request::input('due_date', '');
        $status = (string) Request::input('status', 'pending');
        $completedDate = Request::input('completed_date', null);
        $completedDate = $completedDate !== null && $completedDate !== '' ? (string) $completedDate : null;
        $notes = trim((string) Request::input('notes', '')) ?: null;
        $version = (int) Request::input('version', 1);

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) Response::error('Kitambulisho batili.', 422);
        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $batchClientUuid)) Response::error('Chagua kundi. / Batch reference required.', 422);
        if ($disease === '' || $vaccineName === '') Response::error('Taarifa za chanjo hazijakamilika. / Missing vaccination details.', 422);
        if (!$dueDate || !strtotime($dueDate)) Response::error('Tarehe si sahihi. / Invalid due date.', 422);
        if (!in_array($status, self::STATUSES, true)) Response::error('Hali si sahihi. / Invalid status.', 422);
        if ($status === 'done' && $completedDate && !strtotime($completedDate)) Response::error('Tarehe ya kukamilika si sahihi. / Invalid completed date.', 422);

        $result = VaccinationRecord::upsert((int) $farm['id'], [
            'client_uuid' => $clientUuid, 'batch_client_uuid' => $batchClientUuid,
            'disease' => $disease, 'vaccine_name' => $vaccineName, 'age_days' => $ageDays,
            'due_date' => $dueDate, 'status' => $status, 'completed_date' => $completedDate, 'notes' => $notes,
            'version' => $version,
        ]);

        if ($result['status'] === 'conflict') {
            Response::json([
                'success' => false,
                'conflict' => true,
                'message' => 'Rekodi hii ilibadilishwa mahali pengine kabla. / This record was already changed elsewhere.',
                'server_record' => $result['record'],
            ], 409);
        }

        Response::json(['success' => true, 'record' => $result['record'], 'was_created' => $result['status'] === 'created']);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['records' => []]);
        Response::json(['records' => VaccinationRecord::listByFarm((int) $farm['id'])]);
    }
}
