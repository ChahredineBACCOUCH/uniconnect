<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\Admin\AnneeController;
use App\Http\Controllers\Admin\ClasseController;
use App\Http\Controllers\Admin\CompteAutoriseController;
use App\Http\Controllers\Admin\DepartementController;
use App\Http\Controllers\Admin\FiliereController;
use App\Http\Controllers\Admin\GroupeController;
use App\Http\Controllers\Admin\ImportCompteController;
use App\Http\Controllers\Admin\MatiereController;
use App\Http\Controllers\Admin\NiveauController;
use App\Http\Controllers\AssistantIAController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChapitreController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MembreController;
use App\Http\Controllers\MesEspacesController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RessourceController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Middleware\VerifierLienActivation;
use App\Http\Resources\UtilisateurResource;
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

// Objet `Utilisateur` du contrat d'API (docs/api.md), sans enveloppe `data` :
// le store Pinia `auth` le range tel quel dans `auth.user` (droits compris).
Route::get('/me', function (Request $request) {
    return response()->json(UtilisateurResource::make($request->user())->resolve($request));
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

// SCRUM-66 : activation du compte (choix du mot de passe) et renvoi du lien.
// Le lien reçu par e-mail est signé pour la route GET ci-dessus : le middleware
// VerifierLienActivation en contrôle la signature et l'expiration.
Route::post('/auth/activate/renvoyer', [ActivationController::class, 'renvoyer'])
    ->middleware('throttle:5,1');

Route::post('/auth/activate/{user}', [ActivationController::class, 'store'])
    ->middleware(VerifierLienActivation::class);

// SCRUM-69 : connexion. Renvoie un jeton Sanctum et l'utilisateur connecté.
// Cinq mots de passe incorrects bloquent le compte pendant 15 minutes.
Route::post('/auth/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    // SCRUM-69 : déconnexion (le jeton utilisé pour la requête est supprimé).
    Route::post('/auth/logout', [LoginController::class, 'destroy']);

    // SCRUM-85 : mes espaces (menu latéral et accueil), regroupés par type, avec les non-lus.
    Route::get('/me/espaces', [MesEspacesController::class, 'index']);

    // SCRUM-87 : créer un groupe privé, modifier ses informations.
    Route::post('/espaces', [EspaceController::class, 'store']);
    Route::patch('/espaces/{espace}', [EspaceController::class, 'update']);

    // SCRUM-88 : invitations et recherche de personnes à inviter.
    Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
    Route::post('/espaces/{espace}/invitations', [InvitationController::class, 'store']);
    Route::get('/espaces/{espace}/invitations', [InvitationController::class, 'indexEspace']);
    Route::get('/me/invitations', [InvitationController::class, 'mesInvitations']);
    Route::post('/invitations/{invitation}/accepter', [InvitationController::class, 'accepter']);
    Route::post('/invitations/{invitation}/refuser', [InvitationController::class, 'refuser']);
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy']);

    // SCRUM-90 : membres (lister, changer de rôle, retirer, transférer, quitter).
    Route::get('/espaces/{espace}/membres', [MembreController::class, 'index']);
    Route::patch('/espaces/{espace}/membres/{user}', [MembreController::class, 'update']);
    Route::delete('/espaces/{espace}/membres/{user}', [MembreController::class, 'destroy']);
    Route::post('/espaces/{espace}/transferer', [MembreController::class, 'transferer']);
    Route::post('/espaces/{espace}/quitter', [MembreController::class, 'quitter']);

    // SCRUM-94 : discussion d'un espace (lecture, envoi, modification, suppression).
    Route::get('/espaces/{espace}', [EspaceController::class, 'show']);
    Route::get('/espaces/{espace}/messages', [MessageController::class, 'index']);
    Route::post('/espaces/{espace}/messages', [MessageController::class, 'store']);
    Route::patch('/messages/{message}', [MessageController::class, 'update']);
    Route::delete('/messages/{message}', [MessageController::class, 'destroy']);

    // SCRUM-126 : chapitres et ressources pédagogiques (organisation du cours).
    Route::get('/espaces/{espace}/chapitres', [ChapitreController::class, 'index']);
    Route::post('/espaces/{espace}/chapitres', [ChapitreController::class, 'store']);
    Route::post('/espaces/{espace}/chapitres/ordre', [ChapitreController::class, 'reordonner']);
    Route::patch('/chapitres/{chapitre}', [ChapitreController::class, 'update']);
    Route::delete('/chapitres/{chapitre}', [ChapitreController::class, 'destroy']);

    Route::get('/espaces/{espace}/ressources', [RessourceController::class, 'index']);
    Route::post('/espaces/{espace}/ressources', [RessourceController::class, 'store']);
    Route::post('/chapitres/{chapitre}/ressources', [RessourceController::class, 'storeDansChapitre']);
    Route::patch('/ressources/{ressource}', [RessourceController::class, 'update']);
    Route::delete('/ressources/{ressource}', [RessourceController::class, 'destroy']);
    Route::patch('/ressources/{ressource}/deplacer', [RessourceController::class, 'deplacer']);
    Route::get('/ressources/{ressource}/telecharger', [RessourceController::class, 'telecharger'])
        ->name('ressources.telecharger');

    // SCRUM-102 : question à l'Assistant IA (membre, assistant actif, 20 questions par jour).
    Route::post('/espaces/{espace}/ia/questions', [AssistantIAController::class, 'poser']);

    // SCRUM-145 : activer l'assistant et sa consigne (enseignant propriétaire / co-gestionnaire).
    Route::patch('/espaces/{espace}/assistant', [AssistantIAController::class, 'configurer']);

    // SCRUM-121 : notifications de l'utilisateur (invitation, demande d'adhésion,
    // annonce, ressource, signalement traité, retrait d'un espace).
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/lue', [NotificationController::class, 'marquerLue']);
    Route::post('/notifications/tout-lire', [NotificationController::class, 'toutLire']);
});

// SCRUM-72 : gestion des années universitaires (paramétrage).
Route::middleware(['auth:sanctum', 'can:GERER_PARAMETRAGE'])
    ->prefix('admin')
    ->group(function () {
        // Seuls les endpoints documentés sont exposés (pas de suppression d'année).
        Route::apiResource('annees', AnneeController::class)->only(['index', 'store', 'show', 'update']);
        Route::post('annees/{annee}/activer', [AnneeController::class, 'activer'])
            ->name('admin.annees.activer');
    });

// SCRUM-75 : liste des personnes autorisées à créer un compte.
Route::middleware(['auth:sanctum', 'can:GERER_UTILISATEURS'])
    ->prefix('admin')
    ->group(function () {
        // SCRUM-76 : import Excel des comptes autorisés (aperçu puis validation).
        Route::post('comptes-autorises/import/apercu', [ImportCompteController::class, 'apercu'])
            ->name('admin.comptes-autorises.import.apercu');
        Route::post('comptes-autorises/import/valider', [ImportCompteController::class, 'valider'])
            ->name('admin.comptes-autorises.import.valider');
        Route::get('comptes-autorises/import/modele', [ImportCompteController::class, 'modele'])
            ->name('admin.comptes-autorises.import.modele');

        // SCRUM-77 : classes de l'année active pour les filtres de l'écran A03.
        Route::get('comptes-autorises/classes', [CompteAutoriseController::class, 'classes'])
            ->name('admin.comptes-autorises.classes');

        Route::apiResource('comptes-autorises', CompteAutoriseController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['comptes-autorises' => 'compte_autorise']);

        Route::post('comptes-autorises/{compte_autorise}/desactiver', [CompteAutoriseController::class, 'desactiver'])
            ->name('admin.comptes-autorises.desactiver');
    });

// SCRUM-79 : CRUD des cinq référentiels pédagogiques (paramétrage).
Route::middleware(['auth:sanctum', 'can:GERER_PARAMETRAGE'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('departements', DepartementController::class)
            ->parameters(['departements' => 'departement']);
        Route::patch('departements/{departement}/desactiver', [DepartementController::class, 'desactiver'])
            ->name('admin.departements.desactiver');

        Route::apiResource('filieres', FiliereController::class)
            ->parameters(['filieres' => 'filiere']);
        Route::patch('filieres/{filiere}/desactiver', [FiliereController::class, 'desactiver'])
            ->name('admin.filieres.desactiver');

        Route::apiResource('niveaux', NiveauController::class)
            ->parameters(['niveaux' => 'niveau']);
        Route::patch('niveaux/{niveau}/desactiver', [NiveauController::class, 'desactiver'])
            ->name('admin.niveaux.desactiver');

        Route::apiResource('classes', ClasseController::class)
            ->parameters(['classes' => 'classe']);
        Route::patch('classes/{classe}/desactiver', [ClasseController::class, 'desactiver'])
            ->name('admin.classes.desactiver');

        Route::get('matieres/enseignants', [MatiereController::class, 'enseignants'])
            ->name('admin.matieres.enseignants');

        Route::apiResource('matieres', MatiereController::class)
            ->parameters(['matieres' => 'matiere']);
        Route::patch('matieres/{matiere}/desactiver', [MatiereController::class, 'desactiver'])
            ->name('admin.matieres.desactiver');
    });

// SCRUM-82 / SCRUM-83 : groupes organisationnels de l'année active.
Route::middleware(['auth:sanctum', 'can:CREER_GROUPE_ORGA'])
    ->prefix('admin')
    ->group(function () {
        Route::get('groupes', [GroupeController::class, 'index'])
            ->name('admin.groupes.index');
        Route::get('groupes/filtres', [GroupeController::class, 'filtres'])
            ->name('admin.groupes.filtres');
        Route::get('groupes/apercu', [GroupeController::class, 'apercu'])
            ->name('admin.groupes.apercu');
        Route::post('groupes/generer', [GroupeController::class, 'generer'])
            ->name('admin.groupes.generer');
        Route::post('groupes/{espace}/synchroniser', [GroupeController::class, 'synchroniser'])
            ->name('admin.groupes.synchroniser');
        Route::post('groupes/{espace}/archiver', [GroupeController::class, 'archiver'])
            ->name('admin.groupes.archiver');
    });
