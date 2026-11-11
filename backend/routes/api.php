<?php

use App\Http\Controllers\Admin\AnneeController;
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

Route::middleware(['auth:sanctum', 'can:GERER_PARAMETRAGE'])
    ->prefix('admin')
    ->group(function () {
        // Seuls les endpoints documentés sont exposés (pas de suppression d'année).
        Route::apiResource('annees', AnneeController::class)->only(['index', 'store', 'show', 'update']);
        Route::post('annees/{annee}/activer', [AnneeController::class, 'activer'])
            ->name('admin.annees.activer');
    });
