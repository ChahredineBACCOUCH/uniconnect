<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\DepartementResource;
use App\Models\Departement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * SCRUM-79 : CRUD des départements (référentiel pédagogique).
 */
class DepartementController extends ReferentielController
{
    /**
     * @var array<string, string>
     */
    private const MESSAGES = [
        'code.required' => 'Le code est obligatoire.',
        'code.string' => 'Le code est invalide.',
        'code.max' => 'Le code ne peut pas dépasser 20 caractères.',
        'code.unique' => 'Ce code est déjà utilisé par un département.',
        'libelle.required' => 'Le libellé est obligatoire.',
        'libelle.string' => 'Le libellé est invalide.',
        'libelle.max' => 'Le libellé ne peut pas dépasser 120 caractères.',
        'actif.boolean' => "L'état est invalide.",
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->lister(
            $request,
            Departement::query()->orderBy('code'),
            DepartementResource::class,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('departements', 'code')],
            'libelle' => ['required', 'string', 'max:120'],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $departement = Departement::create([
            ...$donnees,
            'actif' => $donnees['actif'] ?? true,
        ]);

        return (new DepartementResource($departement))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Departement $departement): DepartementResource
    {
        return new DepartementResource($departement);
    }

    public function update(Request $request, Departement $departement): DepartementResource
    {
        $donnees = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('departements', 'code')->ignore($departement->getKey()),
            ],
            'libelle' => ['sometimes', 'required', 'string', 'max:120'],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $departement->update($donnees);

        return new DepartementResource($departement);
    }

    public function destroy(Departement $departement): JsonResponse
    {
        return $this->supprimer($departement);
    }

    public function desactiver(Departement $departement): DepartementResource
    {
        $departement->update(['actif' => false]);

        return new DepartementResource($departement);
    }
}
