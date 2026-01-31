<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DssController;
use App\Http\Controllers\NotificationController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('users/me', [AuthController::class, 'me']);

    Route::get('internships', [InternshipController::class, 'index']);
    Route::post('internships', [InternshipController::class, 'store']);
    Route::get('internships/{internship}', [InternshipController::class, 'show']);
    Route::patch('internships/{internship}', [InternshipController::class, 'update']);
    Route::delete('internships/{internship}', [InternshipController::class, 'destroy']);

    Route::post('applications', [ApplicationController::class, 'store']);
    Route::patch('applications/{application}/status', [ApplicationController::class, 'updateStatus']);

    Route::get('documents', [DocumentController::class, 'index']);
    Route::post('documents', [DocumentController::class, 'store']);

    Route::post('dss/match', [DssController::class, 'match']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
