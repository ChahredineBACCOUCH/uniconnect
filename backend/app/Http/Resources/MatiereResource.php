<?php

namespace App\Http\Resources;

use App\Models\Matiere;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-79 : matière du référentiel pédagogique, avec ses classes et ses
 * enseignants (tables pivot `classe_matiere` et `matiere_enseignant`).
 *
 * @mixin Matiere
 */
class MatiereResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'semestre' => $this->semestre,
            'actif' => $this->actif,
            'classes' => $this->whenLoaded('classes', fn (): array => $this->classes
                ->map(fn ($classe): array => [
                    'id' => $classe->id,
                    'code' => $classe->code,
                    'libelle' => $classe->libelle,
                ])
                ->all()),
            'enseignants' => $this->whenLoaded('enseignants', fn (): array => $this->enseignants
                ->map(fn ($enseignant): array => [
                    'id' => $enseignant->id,
                    'nom' => $enseignant->nom,
                    'prenom' => $enseignant->prenom,
                    'email' => $enseignant->email,
                ])
                ->all()),
        ];
    }
}
