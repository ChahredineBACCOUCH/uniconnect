<?php

namespace App\Http\Resources;

use App\Models\Niveau;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-79 : niveau du référentiel pédagogique.
 *
 * @mixin Niveau
 */
class NiveauResource extends JsonResource
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
        ];
    }
}
