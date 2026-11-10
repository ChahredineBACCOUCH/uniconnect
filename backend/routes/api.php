<?php

use App\Http\Controllers\MessageController;
use App\Http\Controllers\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::select('select 1');
        $db = 'ok';
    } catch (Throwable $e) {
        $db = 'erreur';
    }

    return response()->json([
        'status' => 'ok',
        'version' => config('app.version'),
        'db' => $db,
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// SCRUM-62 : contrôle d'éligibilité avant création de compte.
Route::post('/auth/register', RegisterController::class);

// SCRUM-94 : discussion d'un espace (lecture, envoi, modification, suppression).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/espaces/{espace}/messages', [MessageController::class, 'index']);
    Route::post('/espaces/{espace}/messages', [MessageController::class, 'store']);
    Route::patch('/messages/{message}', [MessageController::class, 'update']);
    Route::delete('/messages/{message}', [MessageController::class, 'destroy']);
});
