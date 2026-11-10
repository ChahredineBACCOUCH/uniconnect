<?php

namespace App\Http\Resources;

use App\Models\Espace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-95 : informations d'un espace, avec le rôle de l'utilisateur courant.
 *
 * @mixin Espace
 */
class EspaceResource extends JsonResource
{
    /**
     * Espace vu par un utilisateur : ajoute son rôle et le nombre de membres
     * (attributs lus par toArray()).
     */
    public static function pour(Espace $espace, User $user): self
    {
        $espace->role = $espace->adhesions()
            ->where('user_id', $user->id)
            ->value('role');
        $espace->nb_membres = $espace->adhesions()->count();

        return new self($espace);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'nom' => $this->nom,
            'description' => $this->description,
            'image' => $this->image,
            'etat' => $this->etat?->value,
            // Calculés par EspaceController@show pour l'utilisateur connecté.
            'role' => $this->role,
            'nb_membres' => $this->nb_membres,
            'duree_visibilite_heures' => $this->duree_visibilite_heures,
            'duree_courte_autorisee' => $this->duree_courte_autorisee,
            'mode_annonce' => $this->mode_annonce,
            // L'assistant IA (SCRUM-101) n'est pas encore livré.
            'assistant' => [
                'actif' => false,
                'instructions' => null,
            ],
        ];
    }
}
