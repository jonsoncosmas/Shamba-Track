<?php

namespace ShambaTrack\Controllers;

use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Models\Currency;

class CurrencyController
{
    public static function search(): void
    {
        $query = trim((string) Request::input('q', ''));
        $results = Currency::search($query, 25);
        Response::json(['currencies' => $results]);
    }
}
