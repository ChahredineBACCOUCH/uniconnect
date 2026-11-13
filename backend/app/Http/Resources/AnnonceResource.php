<?php

namespace App\Http\Resources;

use App\Models\Annonce;
use App\Models\PieceJointe;
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
            'notifier_email' => $this->notifier_email,
            'cree_le' => $this->created_at?->toIso8601String(),
            // SCRUM-124 : pièces jointes affichées avec l'annonce.
            'pieces_jointes' => $this->relationLoaded('piecesJointes')
                ? $this->piecesJointes->map(fn (PieceJointe $piece) => [
                    'id' => $piece->id,
                    'nom' => $piece->nom,
                    'mime' => $piece->mime,
                    'taille_octets' => $piece->taille_octets,
                ])->values()
                : [],
        ];
    }
}
