<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\MatiereResource;
use App\Models\Matiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * SCRUM-79 : CRUD des matières (référentiel pédagogique).
 *
 * `classe_ids[]` et `enseignant_ids[]` sont synchronisés sur les tables pivot
 * `classe_matiere` et `matiere_enseignant`.
 */
class MatiereController extends ReferentielController
{
    /** Colonnes propres à la table `matieres`. */
    private const COLONNES = ['code', 'libelle', 'semestre', 'actif'];

    /**
     * @var array<string, string>
     */
    private const MESSAGES = [
        'code.required' => 'Le code est obligatoire.',
        'code.string' => 'Le code est invalide.',
        'code.max' => 'Le code ne peut pas dépasser 30 caractères.',
        'code.unique' => 'Ce code est déjà utilisé par une matière.',
        'libelle.required' => 'Le libellé est obligatoire.',
        'libelle.string' => 'Le libellé est invalide.',
        'libelle.max' => 'Le libellé ne peut pas dépasser 160 caractères.',
        'semestre.required' => 'Le semestre est obligatoire.',
        'semestre.integer' => 'Le semestre est invalide.',
        'semestre.between' => 'Le semestre doit être 1 ou 2.',
        'classe_ids.array' => 'La liste des classes est invalide.',
        'classe_ids.*.integer' => 'Une des classes est invalide.',
        'classe_ids.*.distinct' => 'Une classe est présente plusieurs fois.',
        'classe_ids.*.exists' => "Une des classes choisies n'existe pas.",
        'enseignant_ids.array' => 'La liste des enseignants est invalide.',
        'enseignant_ids.*.integer' => 'Un des enseignants est invalide.',
        'enseignant_ids.*.distinct' => 'Un enseignant est présent plusieurs fois.',
        'enseignant_ids.*.exists' => "Un des enseignants choisis n'existe pas.",
        'actif.boolean' => "L'état est invalide.",
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->lister(
            $request,
            Matiere::query()->with(['classes', 'enseignants'])->orderBy('code'),
            MatiereResource::class,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('matieres', 'code')],
            'libelle' => ['required', 'string', 'max:160'],
            'semestre' => ['required', 'integer', 'between:1,2'],
            'classe_ids' => ['sometimes', 'nullable', 'array'],
            'classe_ids.*' => ['integer', 'distinct', Rule::exists('classes', 'id')],
            'enseignant_ids' => ['sometimes', 'nullable', 'array'],
            'enseignant_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $matiere = DB::transaction(function () use ($donnees): Matiere {
            $matiere = Matiere::create([
                ...Arr::only($donnees, self::COLONNES),
                'actif' => $donnees['actif'] ?? true,
            ]);

            $matiere->classes()->sync($donnees['classe_ids'] ?? []);
            $matiere->enseignants()->sync($donnees['enseignant_ids'] ?? []);

            return $matiere;
        });

        return (new MatiereResource($matiere->load(['classes', 'enseignants'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Matiere $matiere): MatiereResource
    {
        return new MatiereResource($matiere->load(['classes', 'enseignants']));
    }

    public function update(Request $request, Matiere $matiere): MatiereResource
    {
        $donnees = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('matieres', 'code')->ignore($matiere->getKey()),
            ],
            'libelle' => ['sometimes', 'required', 'string', 'max:160'],
            'semestre' => ['sometimes', 'required', 'integer', 'between:1,2'],
            'classe_ids' => ['sometimes', 'nullable', 'array'],
            'classe_ids.*' => ['integer', 'distinct', Rule::exists('classes', 'id')],
            'enseignant_ids' => ['sometimes', 'nullable', 'array'],
            'enseignant_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        DB::transaction(function () use ($matiere, $donnees): void {
            $matiere->update(Arr::only($donnees, self::COLONNES));

            if (array_key_exists('classe_ids', $donnees)) {
                $matiere->classes()->sync($donnees['classe_ids'] ?? []);
            }

            if (array_key_exists('enseignant_ids', $donnees)) {
                $matiere->enseignants()->sync($donnees['enseignant_ids'] ?? []);
            }
        });

        return new MatiereResource($matiere->refresh()->load(['classes', 'enseignants']));
    }

    public function destroy(Matiere $matiere): JsonResponse
    {
        return $this->supprimer($matiere);
    }

    public function desactiver(Matiere $matiere): MatiereResource
    {
        $matiere->update(['actif' => false]);

        return new MatiereResource($matiere->refresh()->load(['classes', 'enseignants']));
    }
}
