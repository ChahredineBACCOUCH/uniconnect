<?php

namespace App\Http\Resources;

use App\Models\DroitUtilisateur;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Objet `Utilisateur` de docs/api.md (renvoyé par /user, et par /login en SCRUM-69).
 *
 * Contient `droits` (lu par `auth.hasDroit()` côté front) et seulement les champs
 * utiles à l'interface : jamais le mot de passe, le compteur d'échecs ni les
 * dates de blocage.
 *
 * @mixin User
 */
class UtilisateurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['classe', 'droits']);

        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'profil' => $this->profil?->value,
            'classe' => $this->classe === null ? null : [
                'id' => $this->classe->id,
                'libelle' => $this->classe->libelle,
            ],
            'photo' => $this->photo,
            'droits' => $this->droits
                ->map(fn (DroitUtilisateur $d) => $d->droit->value)
                ->values(),
        ];
    }
}
