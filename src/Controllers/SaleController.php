<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\Sale;
use ShambaTrack\Models\Farm;

class SaleController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $batchClientUuid = Request::input('batch_client_uuid', null);
        $batchClientUuid = ($batchClientUuid !== null && $batchClientUuid !== '') ? (string) $batchClientUuid : null;
        $saleType = (string) Request::input('sale_type', '');
        $quantity = (float) Request::input('quantity', 0);
        $unit = trim((string) Request::input('unit', '')) ?: null;
        $weightKg = Request::input('weight_kg', null);
        $weightKg = ($weightKg !== null && $weightKg !== '') ? (float) $weightKg : null;
        $unitPrice = (float) Request::input('unit_price', 0);
        $totalAmount = (float) Request::input('total_amount', 0);
        $buyer = trim((string) Request::input('buyer', '')) ?: null;
        $dateSold = (string) Request::input('date_sold', '');
        $notes = trim((string) Request::input('notes', '')) ?: null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) Response::error('Kitambulisho batili.', 422);
        if ($batchClientUuid !== null && !preg_match('/^[0-9a-fA-F-]{36}$/', $batchClientUuid)) {
            Response::error('Kitambulisho cha kundi si sahihi. / Invalid batch reference.', 422);
        }
        if (!Sale::isValidSaleType($saleType)) Response::error('Chagua aina sahihi ya mauzo. / Select a valid sale type.', 422);
        if ($quantity <= 0) Response::error('Kiasi lazima kiwe zaidi ya sifuri. / Quantity must be greater than zero.', 422);
        if ($unitPrice < 0 || $totalAmount < 0) Response::error('Bei haiwezi kuwa hasi. / Price cannot be negative.', 422);
        if (!$dateSold || !strtotime($dateSold)) Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);

        $result = Sale::createIfNew((int) $farm['id'], [
            'client_uuid' => $clientUuid, 'batch_client_uuid' => $batchClientUuid, 'sale_type' => $saleType,
            'quantity' => $quantity, 'unit' => $unit, 'weight_kg' => $weightKg, 'unit_price' => $unitPrice,
            'total_amount' => $totalAmount, 'buyer' => $buyer, 'date_sold' => $dateSold, 'notes' => $notes,
        ]);
        Response::json(['success' => true, 'record' => $result['record'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['records' => []]);
        Response::json(['records' => Sale::listByFarm((int) $farm['id'])]);
    }
}
