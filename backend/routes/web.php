<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $spaEntry = public_path('index.html');

    if (file_exists($spaEntry)) {
        return response()->file($spaEntry);
    }

    return view('welcome');
});

Route::get('/{path}', function () {
    $spaEntry = public_path('index.html');

    if (file_exists($spaEntry)) {
        return response()->file($spaEntry);
    }

    return view('welcome');
})->where('path', '^(?!api(?:/|$)).*');
