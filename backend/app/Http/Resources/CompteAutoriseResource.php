<?php

namespace App\Http\Resources;

use App\Models\CompteAutorise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-75 : autorisation d'inscription renvoyée par l'API d'administration.
 *
 * @mixin CompteAutorise
 */
class CompteAutoriseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'etat' => $this->etat?->value,
        ];
    }
}
