<?php

namespace App\Http\Resources;

use App\Models\AnneeUniversitaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnneeUniversitaire
 */
class AnneeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'active' => $this->active,
            // Renseignés par la liste (withCount) ; 0 ailleurs.
            'nombre_groupes' => (int) ($this->nombre_groupes ?? 0),
            'nombre_groupes_archives' => (int) ($this->nombre_groupes_archives ?? 0),
        ];
    }
}
