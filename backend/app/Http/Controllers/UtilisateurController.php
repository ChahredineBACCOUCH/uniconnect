<?php

namespace App\Http\Controllers;

use App\Enums\EtatCompte;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SCRUM-88 : recherche de personnes à inviter (`GET /api/utilisateurs?q=`).
 *
 * Seulement les comptes actifs, 10 résultats au maximum, sans l'utilisateur
 * connecté. On ne renvoie ni e-mail ni matricule (données personnelles).
 */
class UtilisateurController extends Controller
{
    private const MAX_RESULTATS = 10;

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ], [
            'q.required' => 'Saisissez au moins 2 caractères pour rechercher.',
            'q.min' => 'Saisissez au moins 2 caractères pour rechercher.',
        ])['q']);

        $motif = '%'.mb_strtolower($q).'%';

        $utilisateurs = User::query()
            ->with('classe')
            ->where('etat', EtatCompte::ACTIF)
            ->whereKeyNot($request->user()->id)
            ->where(function ($requete) use ($motif) {
                $requete->whereRaw('lower(nom) like ?', [$motif])
                    ->orWhereRaw('lower(prenom) like ?', [$motif])
                    ->orWhereRaw("lower(prenom || ' ' || nom) like ?", [$motif])
                    ->orWhereRaw("lower(nom || ' ' || prenom) like ?", [$motif]);
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->limit(self::MAX_RESULTATS)
            ->get();

        return response()->json([
            'data' => $utilisateurs->map(fn (User $u) => [
                'id' => $u->id,
                'nom' => $u->nom,
                'prenom' => $u->prenom,
                'profil' => $u->profil?->value,
                'classe' => $u->classe?->libelle,
            ])->values(),
        ]);
    }
}
