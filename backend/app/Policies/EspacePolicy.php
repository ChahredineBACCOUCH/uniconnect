<?php

namespace App\Policies;

use App\Enums\RoleMembre;
use App\Models\Espace;
use App\Models\User;

/**
 * SCRUM-87 (et SCRUM-94) : qui peut voir, modifier et gérer un espace.
 *
 * - view : être membre (un non-membre reçoit 403 : l'espace existe, l'accès est refusé) ;
 * - update, manageMembers : propriétaire ou co-gestionnaire ;
 * - delete : propriétaire uniquement.
 *
 * Tous les contrôleurs d'un espace (messages, membres, fichiers…) appellent
 * `$this->authorize('view', $espace)` avant de répondre.
 */
class EspacePolicy
{
    public function view(User $user, Espace $espace): bool
    {
        return $this->role($user, $espace) !== null;
    }

    public function update(User $user, Espace $espace): bool
    {
        return $this->estGestionnaire($user, $espace);
    }

    public function manageMembers(User $user, Espace $espace): bool
    {
        return $this->estGestionnaire($user, $espace);
    }

    public function delete(User $user, Espace $espace): bool
    {
        return $this->role($user, $espace) === RoleMembre::PROPRIETAIRE;
    }

    private function estGestionnaire(User $user, Espace $espace): bool
    {
        return in_array(
            $this->role($user, $espace),
            [RoleMembre::PROPRIETAIRE, RoleMembre::CO_GESTIONNAIRE],
            true,
        );
    }

    /** Rôle de l'utilisateur dans l'espace, ou `null` s'il n'en est pas membre. */
    private function role(User $user, Espace $espace): ?RoleMembre
    {
        return $espace->adhesions()
            ->where('user_id', $user->id)
            ->first()
            ?->role;
    }
}
