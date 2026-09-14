<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\CostEntry;
use ShambaTrack\Models\Farm;

class CostEntryController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $category = (string) Request::input('category', '');
        $subType = Request::input('sub_type', null);
        $subType = $subType !== null && $subType !== '' ? (string) $subType : null;
        $label = trim((string) Request::input('label', ''));
        $amount = (float) Request::input('amount', 0);
        $dateIncurred = (string) Request::input('date_incurred', '');
        $notes = trim((string) Request::input('notes', '')) ?: null;
        $batchClientUuid = Request::input('batch_client_uuid', null);
        $batchClientUuid = ($batchClientUuid !== null && $batchClientUuid !== '') ? (string) $batchClientUuid : null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) Response::error('Kitambulisho batili.', 422);
        if ($batchClientUuid !== null && !preg_match('/^[0-9a-fA-F-]{36}$/', $batchClientUuid)) Response::error('Kitambulisho cha kundi si sahihi.', 422);
        if (!CostEntry::isValidCategory($category)) Response::error('Chagua aina sahihi. / Select a valid category.', 422);
        if ($category === 'labor' && !CostEntry::isValidLaborSubtype($subType)) {
            Response::error('Chagua posho au mshahara. / Specify allowance or salary.', 422);
        }
        if ($label === '') Response::error('Weka maelezo mafupi. / Enter a short label.', 422);
        if ($amount < 0) Response::error('Kiasi hakiwezi kuwa hasi. / Amount cannot be negative.', 422);
        if (!$dateIncurred || !strtotime($dateIncurred)) Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);

        $result = CostEntry::createIfNew((int) $farm['id'], [
            'client_uuid' => $clientUuid, 'batch_client_uuid' => $batchClientUuid, 'category' => $category,
            'sub_type' => $category === 'labor' ? $subType : null, 'label' => $label,
            'amount' => $amount, 'date_incurred' => $dateIncurred, 'notes' => $notes,
        ]);
        Response::json(['success' => true, 'record' => $result['record'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['records' => []]);
        Response::json(['records' => CostEntry::listByFarm((int) $farm['id'])]);
    }
}
