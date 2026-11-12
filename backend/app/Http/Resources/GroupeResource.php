<?php

namespace App\Http\Resources;

use App\Models\Espace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SCRUM-83 : groupe organisationnel de l'écran A06.
 *
 * @mixin Espace
 */
class GroupeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proprietaire = $this->adhesions->first()?->user;

        // Un groupe de matière couvre toutes les classes de l'année qui
        // suivent cette matière (chargées par le contrôleur).
        $classes = $this->classe !== null
            ? collect([$this->classe])
            : ($this->matiere?->classes ?? collect());

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'type' => $this->classe_id === null ? 'MATIERE' : 'CLASSE',
            'annee_id' => $this->annee_id,
            'etat' => $this->etat?->value,
            'classe' => $this->classe === null ? null : [
                'id' => $this->classe->id,
                'code' => $this->classe->code,
                'libelle' => $this->classe->libelle,
                'filiere_id' => $this->classe->filiere_id,
                'niveau_id' => $this->classe->niveau_id,
            ],
            'matiere' => $this->matiere === null ? null : [
                'id' => $this->matiere->id,
                'code' => $this->matiere->code,
                'libelle' => $this->matiere->libelle,
            ],
            'classes' => $classes
                ->map(fn ($classe): array => [
                    'id' => $classe->id,
                    'code' => $classe->code,
                    'libelle' => $classe->libelle,
                ])
                ->values()
                ->all(),
            'proprietaire' => $proprietaire === null ? null : [
                'id' => $proprietaire->id,
                'nom' => $proprietaire->nom,
                'prenom' => $proprietaire->prenom,
                'nom_complet' => trim($proprietaire->prenom.' '.$proprietaire->nom),
            ],
            'nombre_membres' => (int) $this->nombre_membres,
        ];
    }
}
