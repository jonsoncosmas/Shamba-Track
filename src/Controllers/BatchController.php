<?php
namespace ShambaTrack\Controllers;
use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\Batch;
use ShambaTrack\Models\Farm;

class BatchController
{
    public static function create(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::error('Weka shamba kwanza. / Set up your farm first.', 422);

        $clientUuid = (string) Request::input('client_uuid', '');
        $breed = (string) Request::input('breed', '');
        $quantity = (int) Request::input('quantity', 0);
        $dateAcquired = (string) Request::input('date_acquired', '');
        $costPerBird = (float) Request::input('cost_per_bird', 0);
        $source = trim((string) Request::input('source', '')) ?: null;
        $notes = trim((string) Request::input('notes', '')) ?: null;

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) {
            Response::error('Kitambulisho batili. / Invalid record identifier.', 422);
        }
        if (!Batch::isValidBreed($breed)) {
            Response::error('Chagua aina sahihi ya kuku. / Please select a valid breed.', 422);
        }
        if ($quantity < 1) {
            Response::error('Idadi ya kuku lazima iwe angalau 1. / Quantity must be at least 1.', 422);
        }
        if (!$dateAcquired || !strtotime($dateAcquired)) {
            Response::error('Weka tarehe sahihi. / Please provide a valid date.', 422);
        }
        if ($costPerBird < 0) {
            Response::error('Gharama haiwezi kuwa hasi. / Cost cannot be negative.', 422);
        }

        $result = Batch::createIfNew((int) $farm['id'], [
            'client_uuid'   => $clientUuid,
            'breed'         => $breed,
            'quantity'      => $quantity,
            'date_acquired' => $dateAcquired,
            'cost_per_bird' => $costPerBird,
            'source'        => $source,
            'notes'         => $notes,
        ]);

        Response::json(['success' => true, 'batch' => $result['batch'], 'was_created' => $result['was_created']]);
    }

    public static function list(): void
    {
        $user = Auth::requireUser();
        $farm = Farm::findByOwner($user['user_id']);
        if (!$farm) Response::json(['batches' => []]);
        Response::json(['batches' => Batch::listByFarm((int) $farm['id'])]);
    }
}
