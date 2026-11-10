<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-88 : une invitation, vue par l'invité (/me/invitations) ou par les
 * gestionnaires de l'espace (/espaces/{id}/invitations).
 *
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'etat' => $this->etat->value,
            'espace' => $this->whenLoaded('espace', fn () => [
                'id' => $this->espace->id,
                'nom' => $this->espace->nom,
            ]),
            'invite' => $this->whenLoaded('invite', fn () => $this->personne($this->invite)),
            'invite_par' => $this->whenLoaded('invitePar', fn () => $this->personne($this->invitePar)),
            'expire_le' => $this->expire_le->toIso8601String(),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function personne($user): ?array
    {
        return $user === null ? null : [
            'id' => $user->id,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'profil' => $user->profil?->value,
        ];
    }
}
