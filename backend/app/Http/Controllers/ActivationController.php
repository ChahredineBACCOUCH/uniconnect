<?php

namespace App\Http\Controllers;

use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Http\Requests\ActivateRequest;
use App\Http\Resources\UtilisateurResource;
use App\Mail\ActivationCompte;
use App\Models\User;
use App\Services\LienActivation;
use App\Services\RattachementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * SCRUM-66 : activation du compte (choix du mot de passe) et renvoi du lien.
 *
 * Le lien d'activation est signé et valable 48 h : il est vérifié par le
 * middleware VerifierLienActivation avant d'arriver ici.
 */
class ActivationController extends Controller
{
    /**
     * POST /api/auth/activate/{user} : la personne choisit son mot de passe,
     * son compte devient actif et elle reçoit un jeton pour être connectée.
     */
    public function store(ActivateRequest $request, string $user, RattachementService $rattachement): JsonResponse
    {
        $compte = User::findOrFail($user);

        // Un compte déjà activé ne peut pas l'être une seconde fois :
        // le lien ne sert plus à changer de mot de passe.
        abort_unless($compte->etat === EtatCompte::EN_ATTENTE, 403, 'Ce compte a déjà été activé.');

        $donnees = $request->validated();

        // Un étudiant actif doit être rattaché à une classe (contrainte de la base).
        if ($compte->profil === Profil::ETUDIANT && $compte->classe_id === null) {
            abort(422, "Votre compte n'est rattaché à aucune classe : contactez l'administration.");
        }

        $compte->forceFill([
            'password' => Hash::make($donnees['password']),
            'etat' => EtatCompte::ACTIF,
            'email_verified_at' => now(),
        ])->save();

        // La liste des comptes autorisés suit l'état du compte.
        $compte->compteAutorise()->update(['etat' => EtatCompte::ACTIF]);

        // Droits par défaut du profil (ex. créer un groupe privé, SCRUM-89).
        foreach ($compte->profil->droitsParDefaut() as $droit) {
            $compte->accorderDroit($droit);
        }

        // Rattachement aux groupes de classe et de matières (SCRUM-68).
        $rattachement->rattacher($compte);

        // Jeton Sanctum : la personne est connectée directement.
        $token = $compte->createToken('activation')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => UtilisateurResource::make($compte)->resolve($request),
        ]);
    }

    /**
     * POST /api/auth/activate/renvoyer : renvoie un e-mail d'activation si le
     * compte est encore en attente d'activation.
     *
     * Le message est identique dans tous les cas : on ne révèle pas si une
     * adresse correspond à un compte.
     */
    public function renvoyer(Request $request): JsonResponse
    {
        $donnees = $request->validate(
            ['email' => ['required', 'email']],
            [
                'email.required' => "L'adresse e-mail est obligatoire.",
                'email.email' => "L'adresse e-mail est invalide.",
            ],
        );

        $compte = User::where('email', $donnees['email'])->first();

        if ($compte && $compte->etat === EtatCompte::EN_ATTENTE) {
            Mail::to($compte->email)->send(new ActivationCompte($compte, LienActivation::pour($compte)));
        }

        return response()->json([
            'message' => "Si un compte est en attente d'activation pour cette adresse, un e-mail vient de lui être envoyé.",
        ], 202);
    }
}
