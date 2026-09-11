<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\InfrastructureItem;
use ShambaTrack\Models\Farm;

class InfrastructureController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $category = (string) Request::input('category', '');
        $itemName = trim((string) Request::input('item_name', ''));
        $landStatus = Request::input('land_status', null);
        $landStatus = $landStatus !== null && $landStatus !== '' ? (string) $landStatus : null;
        $amount = (float) Request::input('amount', 0);
        $dateIncurred = (string) Request::input('date_incurred', '');
        $notes = trim((string) Request::input('notes', '')) ?: null;
        $batchId = Request::input('batch_id', null);
        $batchId = $batchId !== null && $batchId !== '' ? (int) $batchId : null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) {
            Response::error('Kitambulisho batili. / Invalid record identifier.', 422);
        }
        if (!InfrastructureItem::isValidCategory($category)) {
            Response::error('Chagua aina sahihi. / Please select a valid category.', 422);
        }
        if ($itemName === '') {
            Response::error('Jina la kipengele linahitajika. / Item name is required.', 422);
        }
        if ($category === 'land' && !InfrastructureItem::isValidLandStatus($landStatus)) {
            Response::error('Chagua kama umenunua au kupanga shamba. / Please specify bought or rented.', 422);
        }
        if ($amount < 0) {
            Response::error('Kiasi hakiwezi kuwa hasi. / Amount cannot be negative.', 422);
        }
        if (!$dateIncurred || !strtotime($dateIncurred)) {
            Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);
        }

        $result = InfrastructureItem::createIfNew((int) $farm['id'], [
            'client_uuid'   => $clientUuid,
            'batch_id'      => $batchId,
            'category'      => $category,
            'item_name'     => $itemName,
            'land_status'   => $category === 'land' ? $landStatus : null,
            'amount'        => $amount,
            'date_incurred' => $dateIncurred,
            'notes'         => $notes,
        ]);

        Response::json(['success' => true, 'item' => $result['item'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['items' => []]);
        Response::json(['items' => InfrastructureItem::listByFarm((int) $farm['id'])]);
    }
}
