<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * SCRUM-121 : une notification de l'utilisateur connecté.
 *
 * Les quatre champs affichés (`type`, `titre`, `texte`, `lien`) viennent de la
 * colonne `data` de la table `notifications`, remplie par `toArray()`.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $donnees = $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $donnees['type'] ?? null,
            'titre' => $donnees['titre'] ?? null,
            'texte' => $donnees['texte'] ?? null,
            'lien' => $donnees['lien'] ?? null,
            'lue' => $this->read_at !== null,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
