<?php

use App\Http\Controllers\EspaceController;
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

// SCRUM-62/63 : contrôle d'éligibilité, création du compte en attente et
// envoi de l'e-mail d'activation. Route publique : 10 essais par minute et par
// adresse IP, pour empêcher de tester en masse qui figure dans la liste.
Route::post('/auth/register', [RegisterController::class, 'store'])
    ->middleware('throttle:10,1');

// Route signée (48 h) : elle sert à générer le lien avec
// URL::temporarySignedRoute('activation', …, ['user' => $user->id]) et, lorsqu'on
// clique dessus, à ouvrir la page Vue /activation/{id} avec les paramètres signés.
Route::get('/auth/activation/{user}', function (Request $request, string $user) {
    $requete = $request->getQueryString();

    return redirect()->to(
        rtrim((string) config('app.frontend_url'), '/').'/activation/'.$user
        .($requete !== '' ? '?'.$requete : '')
    );
})->name('activation')->middleware('signed');

Route::middleware('auth:sanctum')->group(function () {
    // SCRUM-87 : créer un groupe privé, modifier ses informations.
    Route::post('/espaces', [EspaceController::class, 'store']);
    Route::patch('/espaces/{espace}', [EspaceController::class, 'update']);

    // SCRUM-94 : discussion d'un espace (lecture, envoi, modification, suppression).
    Route::get('/espaces/{espace}', [EspaceController::class, 'show']);
    Route::get('/espaces/{espace}/messages', [MessageController::class, 'index']);
    Route::post('/espaces/{espace}/messages', [MessageController::class, 'store']);
    Route::patch('/messages/{message}', [MessageController::class, 'update']);
    Route::delete('/messages/{message}', [MessageController::class, 'destroy']);
});
