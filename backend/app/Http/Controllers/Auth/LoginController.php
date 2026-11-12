<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EtatCompte;
use App\Http\Controllers\Controller;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * SCRUM-69 : connexion avec un jeton Sanctum et blocage après 5 échecs.
 *
 * Sécurité : cinq mots de passe incorrects bloquent le compte pendant
 * 15 minutes. Un compte désactivé ou suspendu ne peut plus se connecter.
 */
class LoginController extends Controller
{
    /** Nombre de mots de passe incorrects avant le blocage. */
    private const TENTATIVES_MAX = 5;

    /** Durée du blocage, en minutes. */
    private const DUREE_BLOCAGE_MINUTES = 15;

    /**
     * POST /api/auth/login : vérifie les identifiants et renvoie un jeton.
     */
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate(
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ],
            [
                'email.required' => "L'adresse e-mail est obligatoire.",
                'email.email' => "L'adresse e-mail est invalide.",
                'password.required' => 'Le mot de passe est obligatoire.',
            ],
        );

        // L'e-mail ne dépend pas de la casse (majuscule automatique sur mobile) :
        // PostgreSQL compare en respectant la casse, d'où lower() des deux côtés.
        $compte = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($donnees['email']))])
            ->first();

        // Compte inconnu : même message qu'un mot de passe faux, on ne révèle rien.
        if (! $compte) {
            return $this->identifiantsIncorrects();
        }

        // Compte bloqué par les tentatives précédentes.
        if ($compte->bloque_jusqua && $compte->bloque_jusqua->isFuture()) {
            return response()->json([
                'message' => 'Compte bloqué jusqu\'à '.$compte->bloque_jusqua->format('H:i').'.',
            ], 423);
        }

        // Compte désactivé ou suspendu : l'administration doit intervenir.
        if (in_array($compte->etat, [EtatCompte::DESACTIVE, EtatCompte::SUSPENDU], true)) {
            return response()->json([
                'message' => $compte->etat === EtatCompte::SUSPENDU
                    ? 'Ce compte est suspendu : contactez l\'administration.'
                    : 'Ce compte est désactivé : contactez l\'administration.',
            ], 403);
        }

        // Mot de passe incorrect : on compte les échecs.
        if (! Hash::check($donnees['password'], $compte->password)) {
            return $this->enregistrerEchec($compte);
        }

        // Mot de passe correct : on remet le compteur à zéro et on ouvre la session.
        $compte->forceFill([
            'tentatives_echouees' => 0,
            'bloque_jusqua' => null,
            'derniere_connexion' => now(),
        ])->save();

        $token = $compte->createToken('web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => UtilisateurResource::make($compte)->resolve($request),
        ]);
    }

    /**
     * POST /api/auth/logout : supprime le jeton utilisé pour la requête.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Un mot de passe incorrect : au bout de cinq essais, le compte est bloqué
     * pendant quinze minutes et le compteur repart de zéro.
     */
    private function enregistrerEchec(User $compte): JsonResponse
    {
        $tentatives = $compte->tentatives_echouees + 1;

        if ($tentatives >= self::TENTATIVES_MAX) {
            $compte->forceFill([
                'tentatives_echouees' => 0,
                'bloque_jusqua' => now()->addMinutes(self::DUREE_BLOCAGE_MINUTES),
            ])->save();

            return response()->json([
                'message' => 'Trop de tentatives : ce compte est bloqué pendant '
                    .self::DUREE_BLOCAGE_MINUTES.' minutes.',
            ], 423);
        }

        $compte->forceFill(['tentatives_echouees' => $tentatives])->save();

        return response()->json([
            'message' => 'Identifiants incorrects.',
            'tentatives_restantes' => self::TENTATIVES_MAX - $tentatives,
        ], 422);
    }

    /** Réponse d'un compte inconnu ou d'un mot de passe faux. */
    private function identifiantsIncorrects(): JsonResponse
    {
        return response()->json(['message' => 'Identifiants incorrects.'], 422);
    }
}
