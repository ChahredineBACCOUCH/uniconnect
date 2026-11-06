<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::select('select 1');
        $db = 'ok';
    } catch (\Throwable $e) {
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
