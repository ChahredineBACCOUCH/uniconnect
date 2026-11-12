<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ClasseResource;
use App\Models\Classe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * SCRUM-79 : CRUD des classes (référentiel pédagogique).
 *
 * Une classe est unique par code **et** par année universitaire. Elle ne peut
 * pas être supprimée tant que des étudiants y sont rattachés : l'API répond
 * alors **409** et invite à la désactiver.
 */
class ClasseController extends ReferentielController
{
    /**
     * @var array<string, string>
     */
    private const MESSAGES = [
        'code.required' => 'Le code est obligatoire.',
        'code.string' => 'Le code est invalide.',
        'code.max' => 'Le code ne peut pas dépasser 30 caractères.',
        'code.unique' => 'Cette classe existe déjà pour cette année universitaire.',
        'libelle.required' => 'Le libellé est obligatoire.',
        'libelle.string' => 'Le libellé est invalide.',
        'libelle.max' => 'Le libellé ne peut pas dépasser 120 caractères.',
        'filiere_id.required' => 'La filière est obligatoire.',
        'filiere_id.integer' => 'La filière est invalide.',
        'filiere_id.exists' => "La filière choisie n'existe pas.",
        'niveau_id.required' => 'Le niveau est obligatoire.',
        'niveau_id.integer' => 'Le niveau est invalide.',
        'niveau_id.exists' => "Le niveau choisi n'existe pas.",
        'annee_id.required' => "L'année universitaire est obligatoire.",
        'annee_id.integer' => "L'année universitaire est invalide.",
        'annee_id.exists' => "L'année universitaire choisie n'existe pas.",
        'actif.boolean' => "L'état est invalide.",
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->lister(
            $request,
            Classe::query()->with(['filiere', 'niveau', 'annee', 'matieres'])->orderBy('code'),
            ClasseResource::class,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('classes', 'code')
                    ->where(fn ($requete) => $requete->where('annee_id', $request->input('annee_id'))),
            ],
            'libelle' => ['required', 'string', 'max:120'],
            'filiere_id' => ['required', 'integer', Rule::exists('filieres', 'id')],
            'niveau_id' => ['required', 'integer', Rule::exists('niveaux', 'id')],
            'annee_id' => ['required', 'integer', Rule::exists('annees_universitaires', 'id')],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $classe = Classe::create([
            ...$donnees,
            'actif' => $donnees['actif'] ?? true,
        ]);

        return (new ClasseResource($classe->load(['filiere', 'niveau', 'annee', 'matieres'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Classe $classe): ClasseResource
    {
        return new ClasseResource($classe->load(['filiere', 'niveau', 'annee', 'matieres']));
    }

    public function update(Request $request, Classe $classe): ClasseResource
    {
        // Le code est unique dans l'année : la règle porte sur l'année finale,
        // que la mise à jour change ou non.
        $anneeId = $request->input('annee_id', $classe->annee_id);

        $donnees = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('classes', 'code')
                    ->where(fn ($requete) => $requete->where('annee_id', $anneeId))
                    ->ignore($classe->getKey()),
            ],
            'libelle' => ['sometimes', 'required', 'string', 'max:120'],
            'filiere_id' => ['sometimes', 'required', 'integer', Rule::exists('filieres', 'id')],
            'niveau_id' => ['sometimes', 'required', 'integer', Rule::exists('niveaux', 'id')],
            'annee_id' => ['sometimes', 'required', 'integer', Rule::exists('annees_universitaires', 'id')],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $classe->update($donnees);

        return new ClasseResource($classe->refresh()->load(['filiere', 'niveau', 'annee', 'matieres']));
    }

    public function destroy(Classe $classe): JsonResponse
    {
        // La clé étrangère `users.classe_id` est en nullOnDelete : sans ce
        // contrôle, supprimer la classe détacherait ses étudiants en silence.
        return $this->supprimer($classe, $classe->etudiants()->exists());
    }

    public function desactiver(Classe $classe): ClasseResource
    {
        $classe->update(['actif' => false]);

        return new ClasseResource($classe->refresh()->load(['filiere', 'niveau', 'annee', 'matieres']));
    }
}
