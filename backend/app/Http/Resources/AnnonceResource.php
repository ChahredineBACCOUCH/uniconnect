<?php

namespace App\Http\Resources;

use App\Models\Annonce;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-123 : représentation d'une annonce renvoyée par l'API.
 *
 * @mixin Annonce
 */
class AnnonceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'contenu' => $this->contenu,
            'auteur' => $this->auteur === null ? null : [
                'id' => $this->auteur->id,
                'nom' => $this->auteur->nom,
                'prenom' => $this->auteur->prenom,
                'profil' => $this->auteur->profil?->value,
                'badge' => $this->auteur->profil?->libelle(),
            ],
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
