<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\MortalityLog;
use ShambaTrack\Models\Farm;

class MortalityLogController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $batchClientUuid = (string) Request::input('batch_client_uuid', '');
        $dateOccurred = (string) Request::input('date_occurred', '');
        $quantity = (int) Request::input('quantity', 0);
        $cause = trim((string) Request::input('cause', '')) ?: null;
        $notes = trim((string) Request::input('notes', '')) ?: null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) Response::error('Kitambulisho batili.', 422);
        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $batchClientUuid)) Response::error('Chagua kundi la kuku. / Select a batch.', 422);
        if ($quantity <= 0) Response::error('Idadi lazima iwe zaidi ya sifuri. / Quantity must be greater than zero.', 422);
        if (!$dateOccurred || !strtotime($dateOccurred)) Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);

        $result = MortalityLog::createIfNew((int) $farm['id'], [
            'client_uuid' => $clientUuid, 'batch_client_uuid' => $batchClientUuid, 'date_occurred' => $dateOccurred,
            'quantity' => $quantity, 'cause' => $cause, 'notes' => $notes,
        ]);
        Response::json(['success' => true, 'record' => $result['record'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['records' => []]);
        Response::json(['records' => MortalityLog::listByFarm((int) $farm['id'])]);
    }
}
