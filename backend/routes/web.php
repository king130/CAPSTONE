<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $spaEntry = public_path('index.html');

    if (file_exists($spaEntry)) {
        return response()->file($spaEntry);
    }

    return view('welcome');
});

// Do not SPA-fallback crawler/static files that must remain real assets.
Route::get('/{path}', function () {
    $spaEntry = public_path('index.html');

    if (file_exists($spaEntry)) {
        return response()->file($spaEntry);
    }

    return view('welcome');
})->where('path', '^(?!api(?:/|$)|robots\.txt$|sitemap\.xml$|manifest\.webmanifest$|\.well-known(?:/|$)).*');
