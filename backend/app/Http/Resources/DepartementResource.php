<?php

namespace App\Http\Resources;

use App\Models\Departement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-79 : département du référentiel pédagogique.
 *
 * @mixin Departement
 */
class DepartementResource extends JsonResource
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
