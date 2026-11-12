<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EtatEspace;
use App\Enums\TypeEspace;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnneeResource;
use App\Models\AnneeUniversitaire;
use App\Models\Espace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SCRUM-72 : création des années universitaires et activation d'une seule à la fois.
 */
class AnneeController extends Controller
{
    /** Libellé d'une année universitaire : « 2026/2027 ». */
    private const FORMAT_LIBELLE = '#^\d{4}/\d{4}$#';

    /**
     * Messages en français : l'application n'a pas de fichiers de traduction,
     * Laravel afficherait sinon « The libelle field is required. ».
     *
     * @var array<string, string>
     */
    private const MESSAGES = [
        'libelle.required' => 'Le libellé est obligatoire.',
        'libelle.regex' => 'Le libellé doit avoir la forme « 2026/2027 ».',
        'libelle.unique' => 'Cette année universitaire existe déjà.',
        'date_debut.required' => 'La date de début est obligatoire.',
        'date_debut.date' => 'La date de début est invalide.',
        'date_fin.required' => 'La date de fin est obligatoire.',
        'date_fin.date' => 'La date de fin est invalide.',
        'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
        'archiver_precedente.boolean' => "L'option d'archivage est invalide.",
    ];

    /**
     * Liste les années universitaires, de la plus récente à la plus ancienne.
     *
     * Chaque année indique son nombre de groupes organisationnels (écran A01) :
     * total et nombre d'entre eux déjà archivés.
     */
    public function index(): AnonymousResourceCollection
    {
        $annees = AnneeUniversitaire::query()
            ->withCount([
                'espaces as nombre_groupes' => fn (Builder $query) => $query
                    ->where('type', TypeEspace::ORGANISATIONNEL->value),
                'espaces as nombre_groupes_archives' => fn (Builder $query) => $query
                    ->where('type', TypeEspace::ORGANISATIONNEL->value)
                    ->where('etat', EtatEspace::ARCHIVE->value),
            ])
            ->orderByDesc('date_debut')
            ->get();

        return AnneeResource::collection($annees);
    }

    /**
     * Crée une année universitaire (inactive par défaut).
     */
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'regex:'.self::FORMAT_LIBELLE, Rule::unique('annees_universitaires', 'libelle')],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
        ], self::MESSAGES);

        $annee = AnneeUniversitaire::create([
            ...$donnees,
            'active' => false,
        ]);

        return (new AnneeResource($annee))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Affiche une année universitaire.
     */
    public function show(AnneeUniversitaire $annee): AnneeResource
    {
        return new AnneeResource($annee);
    }

    /**
     * Met à jour une année universitaire.
     *
     * La comparaison des dates porte sur les valeurs finales : une mise à jour
     * partielle (par exemple seulement `date_fin`) reste donc cohérente.
     */
    public function update(Request $request, AnneeUniversitaire $annee): AnneeResource
    {
        $donnees = $request->validate([
            'libelle' => [
                'sometimes',
                'required',
                'string',
                'regex:'.self::FORMAT_LIBELLE,
                Rule::unique('annees_universitaires', 'libelle')->ignore($annee->getKey()),
            ],
            'date_debut' => ['sometimes', 'required', 'date'],
            'date_fin' => ['sometimes', 'required', 'date'],
        ], self::MESSAGES);

        $debut = Carbon::parse($donnees['date_debut'] ?? $annee->date_debut);
        $fin = Carbon::parse($donnees['date_fin'] ?? $annee->date_fin);

        if (! $debut->lt($fin)) {
            throw ValidationException::withMessages([
                'date_fin' => 'La date de fin doit être postérieure à la date de début.',
            ]);
        }

        $annee->update($donnees);

        return new AnneeResource($annee);
    }

    /**
     * Active cette année et désactive l'éventuelle année active précédente.
     *
     * L'option `archiver_precedente` passe à l'état ARCHIVE tous les espaces
     * rattachés à l'ancienne année universitaire.
     */
    public function activer(Request $request, AnneeUniversitaire $annee): AnneeResource
    {
        $donnees = $request->validate([
            'archiver_precedente' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        DB::transaction(function () use ($annee, $donnees): void {
            $precedente = AnneeUniversitaire::query()
                ->where('active', true)
                ->where('id', '!=', $annee->getKey())
                ->lockForUpdate()
                ->first();

            // Désactiver d'abord : l'index unique `annee_une_seule_active`
            // interdit que deux années soient actives au même instant.
            AnneeUniversitaire::query()
                ->where('active', true)
                ->update(['active' => false]);

            $annee->update(['active' => true]);

            if (($donnees['archiver_precedente'] ?? false) && $precedente !== null) {
                Espace::query()
                    ->where('annee_id', $precedente->getKey())
                    ->update(['etat' => EtatEspace::ARCHIVE->value]);
            }

            // TODO(SCRUM-72) : historiser l'activation via le service Historique
            // (table `operations`) dès qu'il sera disponible.
        });

        return new AnneeResource($annee->refresh());
    }
}
