<?php

namespace App\Http\Resources;

use App\Models\Message;
use App\Models\PieceJointe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-94 : représentation d'un message renvoyée par l'API.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contenu' => $this->contenu,
            'auteur' => $this->auteur === null ? null : [
                'id' => $this->auteur->id,
                'nom' => $this->auteur->nom,
                'prenom' => $this->auteur->prenom,
                'profil' => $this->auteur->profil?->value,
                // Libellé prêt à afficher dans le chip de la maquette E05.
                'badge' => $this->auteur->profil?->libelle(),
            ],
            'parent_id' => $this->parent_id,
            'expire_le' => $this->expire_le?->toIso8601String(),
            'modifie' => $this->modifie_le !== null,
            'modifie_le' => $this->modifie_le?->toIso8601String(),
            'cree_le' => $this->created_at?->toIso8601String(),
            // Vide tant que SCRUM-136 n'a pas livré la table `pieces_jointes` :
            // on évite de déclencher une requête vers une table inexistante.
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
