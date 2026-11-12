<?php

namespace App\Services;

use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\User;

/**
 * SCRUM-68 / SCRUM-82 : rattachement d'un utilisateur aux groupes
 * organisationnels de sa classe (groupe de classe et groupes de matière).
 *
 * Réutilisé à l'activation d'un compte étudiant. Idempotent : chaque adhésion
 * est créée avec `firstOrCreate` (unique `espace_id + user_id`).
 */
class RattachementService
{
    /**
     * @return int nombre d'adhésions réellement créées
     */
    public function rattacher(User $utilisateur): int
    {
        $classe = $utilisateur->classe;

        if ($classe === null) {
            return 0;
        }

        $espaces = Espace::query()
            ->where('type', TypeEspace::ORGANISATIONNEL->value)
            ->where('annee_id', $classe->annee_id)
            ->where('classe_id', $classe->id)
            ->get();

        $ajoutes = 0;

        foreach ($espaces as $espace) {
            $adhesion = Adhesion::firstOrCreate(
                ['espace_id' => $espace->id, 'user_id' => $utilisateur->id],
                ['role' => RoleMembre::MEMBRE],
            );

            if ($adhesion->wasRecentlyCreated) {
                $ajoutes++;
            }
        }

        return $ajoutes;
    }
}
