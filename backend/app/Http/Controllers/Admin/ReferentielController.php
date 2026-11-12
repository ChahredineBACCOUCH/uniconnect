<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-79 : comportement commun aux cinq référentiels pédagogiques
 * (départements, filières, niveaux, classes, matières).
 *
 * Les listes suivent le format de `docs/api.md` ({ data, meta }) et la
 * suppression d'un élément encore utilisé répond **409** au lieu de laisser
 * remonter l'erreur de clé étrangère.
 */
abstract class ReferentielController extends Controller
{
    /** Message renvoyé quand une suppression est refusée par une contrainte. */
    public const MESSAGE_UTILISE = 'Impossible de supprimer : élément utilisé. Vous pouvez le désactiver.';

    /** Taille de page par défaut des listes (docs/api.md). */
    protected const PAR_PAGE = 20;

    /**
     * Messages en français : l'application n'a pas de fichiers de traduction,
     * Laravel afficherait sinon « The actif field must be true or false. ».
     *
     * @var array<string, string>
     */
    protected const MESSAGES_LISTE = [
        'actif.boolean' => "Le filtre d'état est invalide.",
        'q.string' => 'La recherche est invalide.',
        'q.max' => 'La recherche ne peut pas dépasser 100 caractères.',
        'par_page.integer' => 'Le nombre de lignes par page est invalide.',
        'par_page.min' => 'Le nombre de lignes par page doit être au moins 1.',
        'par_page.max' => 'Le nombre de lignes par page ne peut pas dépasser 100.',
        'page.integer' => 'Le numéro de page est invalide.',
        'page.min' => 'Le numéro de page doit être au moins 1.',
    ];

    /**
     * Liste paginée d'un référentiel : filtre `actif`, recherche `q` sur le
     * code et le libellé.
     *
     * @param  Builder<Model>  $requete
     * @param  class-string<JsonResource>  $ressource
     */
    protected function lister(Request $request, Builder $requete, string $ressource): AnonymousResourceCollection
    {
        $donnees = $request->validate([
            'actif' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], self::MESSAGES_LISTE);

        if (array_key_exists('actif', $donnees)) {
            $requete->where('actif', $request->boolean('actif'));
        }

        $recherche = trim((string) ($donnees['q'] ?? ''));

        if ($recherche !== '') {
            // `whereLike` compile en ILIKE sur PostgreSQL et en LIKE (déjà
            // insensible à la casse pour l'ASCII) sur SQLite.
            $requete->where(function (Builder $sousRequete) use ($recherche): void {
                $sousRequete
                    ->whereLike('code', "%{$recherche}%")
                    ->orWhereLike('libelle', "%{$recherche}%");
            });
        }

        $page = $requete->paginate($donnees['par_page'] ?? self::PAR_PAGE);

        return $ressource::collection($page->getCollection())->additional([
            'meta' => [
                'page' => $page->currentPage(),
                'par_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Supprime l'élément, ou répond **409** s'il est encore utilisé.
     *
     * `$utilise` permet aux contrôleurs de refuser une suppression que la base
     * accepterait sans erreur (par exemple une classe dont les étudiants
     * seraient silencieusement détachés).
     */
    protected function supprimer(Model $element, bool $utilise = false): JsonResponse
    {
        if (! $utilise) {
            try {
                $element->delete();
            } catch (QueryException) {
                // Clé étrangère en RESTRICT : l'élément est encore référencé.
                $utilise = true;
            }
        }

        if ($utilise) {
            return response()->json(['message' => self::MESSAGE_UTILISE], 409);
        }

        return response()->json(null, 204);
    }
}
