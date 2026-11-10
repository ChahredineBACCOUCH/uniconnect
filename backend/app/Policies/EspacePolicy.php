<?php

namespace App\Policies;

use App\Models\Espace;
use App\Models\User;

/**
 * SCRUM-94 : accès à un espace et à son contenu.
 */
class EspacePolicy
{
    /**
     * Consulter un espace et ses messages : il faut en être membre.
     * Un non-membre reçoit un 403 (la ressource existe, mais l'accès est refusé).
     */
    public function view(User $user, Espace $espace): bool
    {
        return $espace->adhesions()
            ->where('user_id', $user->id)
            ->exists();
    }
}
