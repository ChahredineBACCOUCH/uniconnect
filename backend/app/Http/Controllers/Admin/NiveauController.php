<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\NiveauResource;
use App\Models\Niveau;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * SCRUM-79 : CRUD des niveaux (référentiel pédagogique).
 */
class NiveauController extends ReferentielController
{
    /**
     * @var array<string, string>
     */
    private const MESSAGES = [
        'code.required' => 'Le code est obligatoire.',
        'code.string' => 'Le code est invalide.',
        'code.max' => 'Le code ne peut pas dépasser 10 caractères.',
        'code.unique' => 'Ce code est déjà utilisé par un niveau.',
        'libelle.required' => 'Le libellé est obligatoire.',
        'libelle.string' => 'Le libellé est invalide.',
        'libelle.max' => 'Le libellé ne peut pas dépasser 60 caractères.',
        'actif.boolean' => "L'état est invalide.",
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->lister(
            $request,
            Niveau::query()->orderBy('code'),
            NiveauResource::class,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('niveaux', 'code')],
            'libelle' => ['required', 'string', 'max:60'],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $niveau = Niveau::create([
            ...$donnees,
            'actif' => $donnees['actif'] ?? true,
        ]);

        return (new NiveauResource($niveau))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Niveau $niveau): NiveauResource
    {
        return new NiveauResource($niveau);
    }

    public function update(Request $request, Niveau $niveau): NiveauResource
    {
        $donnees = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('niveaux', 'code')->ignore($niveau->getKey()),
            ],
            'libelle' => ['sometimes', 'required', 'string', 'max:60'],
            'actif' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $niveau->update($donnees);

        return new NiveauResource($niveau);
    }

    public function destroy(Niveau $niveau): JsonResponse
    {
        return $this->supprimer($niveau);
    }

    public function desactiver(Niveau $niveau): NiveauResource
    {
        $niveau->update(['actif' => false]);

        return new NiveauResource($niveau);
    }
}
