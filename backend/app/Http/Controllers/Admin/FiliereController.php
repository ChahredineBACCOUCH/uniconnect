<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\FiliereResource;
use App\Models\Filiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * SCRUM-79 : CRUD des filières (référentiel pédagogique).
 */
class FiliereController extends ReferentielController
{
    /**
     * @var array<string, string>
     */
    private const MESSAGES = [
        'code.required' => 'Le code est obligatoire.',
        'code.string' => 'Le code est invalide.',
        'code.max' => 'Le code ne peut pas dépasser 20 caractères.',
        'code.unique' => 'Ce code est déjà utilisé par une filière.',
        'libelle.required' => 'Le libellé est obligatoire.',
        'libelle.string' => 'Le libellé est invalide.',
        'libelle.max' => 'Le libellé ne peut pas dépasser 120 caractères.',
        'departement_id.required' => 'Le département est obligatoire.',
        'departement_id.integer' => 'Le département est invalide.',
        'departement_id.exists' => "Le département choisi n'existe pas.",
        'actif.boolean' => "L'état est invalide.",
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->lister(
            $request,
            Filiere::query()->with('departement')->orderBy('code'),
            FiliereResource::class,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('filieres', 'code')],
            'libelle' => ['required', 'string', 'max:120'],
            'departement_id' => ['required', 'integer', Rule::exists('departements', 'id')],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $filiere = Filiere::create([
            ...$donnees,
            'actif' => $donnees['actif'] ?? true,
        ]);

        return (new FiliereResource($filiere->load('departement')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Filiere $filiere): FiliereResource
    {
        return new FiliereResource($filiere->load('departement'));
    }

    public function update(Request $request, Filiere $filiere): FiliereResource
    {
        $donnees = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('filieres', 'code')->ignore($filiere->getKey()),
            ],
            'libelle' => ['sometimes', 'required', 'string', 'max:120'],
            'departement_id' => ['sometimes', 'required', 'integer', Rule::exists('departements', 'id')],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $filiere->update($donnees);

        return new FiliereResource($filiere->refresh()->load('departement'));
    }

    public function destroy(Filiere $filiere): JsonResponse
    {
        return $this->supprimer($filiere);
    }

    public function desactiver(Filiere $filiere): FiliereResource
    {
        $filiere->update(['actif' => false]);

        return new FiliereResource($filiere->refresh()->load('departement'));
    }
}
