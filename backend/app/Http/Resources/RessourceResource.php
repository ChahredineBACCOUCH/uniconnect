<?php

namespace App\Http\Resources;

use App\Models\Ressource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-126 : ressource pédagogique d'un chapitre.
 *
 * Le chemin de stockage n'est jamais exposé : le téléchargement passe par
 * `GET /api/ressources/{id}/telecharger`.
 *
 * @mixin Ressource
 */
class RessourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chapitre_id' => $this->chapitre_id,
            'titre' => $this->titre,
            'description' => $this->description,
            'mime' => $this->mime,
            'taille' => $this->taille,
            'url' => $this->url,
            'indexee_ia' => $this->indexee_ia,
            'auteur_id' => $this->auteur_id,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
