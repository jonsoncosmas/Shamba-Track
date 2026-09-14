<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\EggLog;
use ShambaTrack\Models\Farm;

class EggLogController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $batchClientUuid = (string) Request::input('batch_client_uuid', '');
        $dateCollected = (string) Request::input('date_collected', '');
        $quantityWhole = (int) Request::input('quantity_whole', 0);
        $quantityBroken = (int) Request::input('quantity_broken', 0);
        $notes = trim((string) Request::input('notes', '')) ?: null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) Response::error('Kitambulisho batili.', 422);
        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $batchClientUuid)) Response::error('Chagua kundi la kuku. / Select a batch.', 422);
        if (!$dateCollected || !strtotime($dateCollected)) Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);
        if ($quantityWhole < 0 || $quantityBroken < 0) Response::error('Idadi haiwezi kuwa hasi. / Quantity cannot be negative.', 422);
        if ($quantityWhole === 0 && $quantityBroken === 0) Response::error('Weka angalau idadi moja. / Enter at least one quantity.', 422);

        $result = EggLog::createIfNew((int) $farm['id'], [
            'client_uuid' => $clientUuid, 'batch_client_uuid' => $batchClientUuid, 'date_collected' => $dateCollected,
            'quantity_whole' => $quantityWhole, 'quantity_broken' => $quantityBroken, 'notes' => $notes,
        ]);
        Response::json(['success' => true, 'record' => $result['record'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['records' => []]);
        Response::json(['records' => EggLog::listByFarm((int) $farm['id'])]);
    }
}
