<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\FeedPurchase;
use ShambaTrack\Models\Farm;

class FeedPurchaseController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $source = (string) Request::input('source', 'bought');
        $feedType = (string) Request::input('feed_type', '');
        $quantityKg = (float) Request::input('quantity_kg', 0);
        $totalCost = (float) Request::input('total_cost', 0);
        $datePurchased = (string) Request::input('date_purchased', '');
        $notes = trim((string) Request::input('notes', '')) ?: null;
        $batchClientUuid = Request::input('batch_client_uuid', null);
        $batchClientUuid = ($batchClientUuid !== null && $batchClientUuid !== '') ? (string) $batchClientUuid : null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) Response::error('Kitambulisho batili.', 422);
        if ($batchClientUuid !== null && !preg_match('/^[0-9a-fA-F-]{36}$/', $batchClientUuid)) Response::error('Kitambulisho cha kundi si sahihi.', 422);
        if (!FeedPurchase::isValidSource($source)) Response::error('Chagua chanzo sahihi. / Select a valid source.', 422);
        if (!FeedPurchase::isValidFeedType($feedType)) Response::error('Chagua aina sahihi ya chakula. / Select a valid feed type.', 422);
        if ($quantityKg <= 0) Response::error('Kiasi lazima kiwe zaidi ya sifuri. / Quantity must be greater than zero.', 422);
        if ($totalCost < 0) Response::error('Gharama haiwezi kuwa hasi. / Cost cannot be negative.', 422);
        if (!$datePurchased || !strtotime($datePurchased)) Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);

        $result = FeedPurchase::createIfNew((int) $farm['id'], [
            'client_uuid' => $clientUuid, 'batch_client_uuid' => $batchClientUuid, 'source' => $source,
            'feed_type' => $feedType, 'quantity_kg' => $quantityKg, 'total_cost' => $totalCost,
            'date_purchased' => $datePurchased, 'notes' => $notes,
        ]);
        Response::json(['success' => true, 'record' => $result['record'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['records' => []]);
        Response::json(['records' => FeedPurchase::listByFarm((int) $farm['id'])]);
    }
}
