<?php

namespace App\Http\Resources;

use App\Models\Filiere;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-79 : filière du référentiel pédagogique, avec son département.
 *
 * @mixin Filiere
 */
class FiliereResource extends JsonResource
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
            'departement' => $this->whenLoaded('departement', fn (): ?array => $this->departement === null ? null : [
                'id' => $this->departement->id,
                'code' => $this->departement->code,
                'libelle' => $this->departement->libelle,
            ]),
        ];
    }
}
