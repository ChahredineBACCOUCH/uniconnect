<?php

namespace App\Http\Resources;

use App\Models\Classe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-79 : classe du référentiel pédagogique, avec sa filière, son niveau,
 * son année universitaire et ses matières.
 *
 * @mixin Classe
 */
class ClasseResource extends JsonResource
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
            'actif' => $this->actif,
            'filiere' => $this->whenLoaded('filiere', fn (): ?array => $this->filiere === null ? null : [
                'id' => $this->filiere->id,
                'code' => $this->filiere->code,
                'libelle' => $this->filiere->libelle,
            ]),
            'niveau' => $this->whenLoaded('niveau', fn (): ?array => $this->niveau === null ? null : [
                'id' => $this->niveau->id,
                'code' => $this->niveau->code,
                'libelle' => $this->niveau->libelle,
            ]),
            'annee' => $this->whenLoaded('annee', fn (): ?array => $this->annee === null ? null : [
                'id' => $this->annee->id,
                'libelle' => $this->annee->libelle,
            ]),
            'matieres' => $this->whenLoaded('matieres', fn (): array => $this->matieres
                ->map(fn ($matiere): array => [
                    'id' => $matiere->id,
                    'code' => $matiere->code,
                    'libelle' => $matiere->libelle,
                ])
                ->all()),
        ];
    }
}
