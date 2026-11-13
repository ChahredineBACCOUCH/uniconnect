<?php

namespace App\Http\Resources;

use App\Models\Chapitre;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-126 : chapitre d'un espace, avec ses ressources quand elles sont chargées.
 *
 * @mixin Chapitre
 */
class ChapitreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'ordre' => $this->ordre,
            'ressources' => RessourceResource::collection($this->whenLoaded('ressources')),
        ];
    }
}
