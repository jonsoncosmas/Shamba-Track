<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\FeedConsumption;
use ShambaTrack\Models\Farm;

class FeedConsumptionController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $batchClientUuid = (string) Request::input('batch_client_uuid', '');
        $quantityKg = (float) Request::input('quantity_kg', 0);
        $dateConsumed = (string) Request::input('date_consumed', '');
        $notes = trim((string) Request::input('notes', '')) ?: null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) Response::error('Kitambulisho batili.', 422);
        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $batchClientUuid)) Response::error('Chagua kundi la kuku. / Select a batch.', 422);
        if ($quantityKg <= 0) Response::error('Kiasi lazima kiwe zaidi ya sifuri. / Quantity must be greater than zero.', 422);
        if (!$dateConsumed || !strtotime($dateConsumed)) Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);

        $result = FeedConsumption::createIfNew((int) $farm['id'], [
            'client_uuid' => $clientUuid, 'batch_client_uuid' => $batchClientUuid,
            'quantity_kg' => $quantityKg, 'date_consumed' => $dateConsumed, 'notes' => $notes,
        ]);
        Response::json(['success' => true, 'record' => $result['record'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['records' => []]);
        Response::json(['records' => FeedConsumption::listByFarm((int) $farm['id'])]);
    }
}
