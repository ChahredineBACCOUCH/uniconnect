<?php

namespace App\Policies;

use App\Enums\RoleMembre;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\Ressource;
use App\Models\User;

/**
 * SCRUM-125 : accès aux ressources pédagogiques d'un espace.
 *
 * - voir : tout membre de l'espace du chapitre ;
 * - créer, modifier, supprimer : propriétaire ou co-gestionnaire de l'espace.
 *
 * Pour la création, le contrôleur autorise la politique de `Ressource` en lui
 * passant le chapitre parent : `$this->authorize('create', [Ressource::class, $chapitre])`.
 */
class RessourcePolicy
{
    public function view(User $user, Ressource $ressource): bool
    {
        return $this->role($user, $this->espace($ressource)) !== null;
    }

    public function create(User $user, Chapitre $chapitre): bool
    {
        return $this->estGestionnaire($user, $chapitre->espace);
    }

    public function update(User $user, Ressource $ressource): bool
    {
        return $this->estGestionnaire($user, $this->espace($ressource));
    }

    public function delete(User $user, Ressource $ressource): bool
    {
        return $this->estGestionnaire($user, $this->espace($ressource));
    }

    private function espace(Ressource $ressource): ?Espace
    {
        return $ressource->chapitre?->espace;
    }

    private function estGestionnaire(User $user, ?Espace $espace): bool
    {
        return in_array(
            $this->role($user, $espace),
            [RoleMembre::PROPRIETAIRE, RoleMembre::CO_GESTIONNAIRE],
            true,
        );
    }

    /** Rôle de l'utilisateur dans l'espace, ou `null` s'il n'en est pas membre. */
    private function role(User $user, ?Espace $espace): ?RoleMembre
    {
        if ($espace === null) {
            return null;
        }

        return $espace->adhesions()
            ->where('user_id', $user->id)
            ->first()
            ?->role;
    }
}
