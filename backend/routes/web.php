<?php

use Illuminate\Support\Facades\Route;

if (! function_exists('spaEntryResponse')) {
    function spaEntryResponse()
    {
        $spaEntry = public_path('index.html');

        if (file_exists($spaEntry)) {
            return response()->file($spaEntry);
        }

        return view('welcome');
    }
}

Route::get('/', function () {
    return spaEntryResponse();
});

Route::get('/{path}', function () {
    return spaEntryResponse();
})->where('path', '^(?!api(?:/|$)).*');
