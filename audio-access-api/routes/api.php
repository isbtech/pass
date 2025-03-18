<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AudioFileController;
use App\Http\Controllers\AccessCodeController;

// Auth routes
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user/current', [UserController::class, 'current']);

// Audio file routes
Route::middleware('auth:sanctum')->prefix('audio')->group(function () {
    Route::post('/upload', [AudioFileController::class, 'upload']);
    Route::get('/list', [AudioFileController::class, 'list']);
    Route::delete('/{id}', [AudioFileController::class, 'delete']);
});

// Access code routes
Route::prefix('access')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/generate', [AccessCodeController::class, 'generate']);
        Route::get('/list', [AccessCodeController::class, 'listCodes']);
        Route::put('/{id}/deactivate', [AccessCodeController::class, 'deactivate']);
        Route::get('/stats', [AccessCodeController::class, 'getStats']);
    });
    
    // Public routes
    Route::post('/verify', [AccessCodeController::class, 'verify']);
    Route::get('/file/{id}', [AccessCodeController::class, 'getAudioFile']);
    Route::post('/stats', [AccessCodeController::class, 'updatePlayStats']);
});