<?php

namespace ShambaTrack\Controllers;

use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\Currency;
use ShambaTrack\Models\Farm;

class FarmController
{
    public static function create(): void
    {
        $user = Auth::requireUser();

        $name = trim((string) Request::input('name', ''));
        $region = trim((string) Request::input('region', '')) ?: null;
        $district = trim((string) Request::input('district', '')) ?: null;
        $village = trim((string) Request::input('village', '')) ?: null;
        $currencyCode = strtoupper(trim((string) Request::input('currency_code', '')));

        if ($name === '') {
            Response::error('Jina la shamba linahitajika. / Farm name is required.', 422);
        }

        if ($currencyCode === '' || !Currency::exists($currencyCode)) {
            Response::error('Chagua sarafu sahihi. / Please select a valid currency.', 422);
        }

        // v1: one farm per owner. If one already exists, don't silently
        // create a duplicate — return the existing one.
        $existing = Farm::findByOwner($user['user_id']);
        if ($existing) {
            Response::json(['success' => true, 'farm' => $existing, 'already_existed' => true]);
        }

        $farm = Farm::create($user['user_id'], [
            'name'          => $name,
            'region'        => $region,
            'district'      => $district,
            'village'       => $village,
            'currency_code' => $currencyCode,
        ]);

        Response::json(['success' => true, 'farm' => $farm, 'already_existed' => false]);
    }
}
