<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\CapitalSource;
use ShambaTrack\Models\Farm;

class CapitalSourceController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $batchClientUuid = Request::input('batch_client_uuid', null);
        $batchClientUuid = ($batchClientUuid !== null && $batchClientUuid !== '') ? (string) $batchClientUuid : null;
        $sourceType = (string) Request::input('source_type', '');
        $amount = (float) Request::input('amount', 0);
        $interestRate = Request::input('interest_rate', null);
        $interestRate = ($interestRate !== null && $interestRate !== '') ? (float) $interestRate : null;
        $dateReceived = (string) Request::input('date_received', '');
        $notes = trim((string) Request::input('notes', '')) ?: null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) Response::error('Kitambulisho batili.', 422);
        if ($batchClientUuid !== null && !preg_match('/^[0-9a-fA-F-]{36}$/', $batchClientUuid)) {
            Response::error('Kitambulisho cha kundi si sahihi. / Invalid batch reference.', 422);
        }
        if (!CapitalSource::isValidSourceType($sourceType)) Response::error('Chagua chanzo sahihi. / Select a valid source type.', 422);
        if ($amount <= 0) Response::error('Kiasi lazima kiwe zaidi ya sifuri. / Amount must be greater than zero.', 422);
        if ($interestRate !== null && ($interestRate < 0 || $interestRate > 100)) {
            Response::error('Riba si sahihi. / Invalid interest rate.', 422);
        }
        if (!$dateReceived || !strtotime($dateReceived)) Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);

        $result = CapitalSource::createIfNew((int) $farm['id'], [
            'client_uuid' => $clientUuid, 'batch_client_uuid' => $batchClientUuid, 'source_type' => $sourceType,
            'amount' => $amount, 'interest_rate' => $sourceType === 'loan' ? $interestRate : null,
            'date_received' => $dateReceived, 'notes' => $notes,
        ]);
        Response::json(['success' => true, 'record' => $result['record'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['records' => []]);
        Response::json(['records' => CapitalSource::listByFarm((int) $farm['id'])]);
    }
}
