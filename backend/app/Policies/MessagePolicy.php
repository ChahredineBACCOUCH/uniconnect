<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * SCRUM-94 : un message n'appartient qu'à son auteur.
 * Seul l'auteur peut le modifier ou le supprimer (le ticket prime sur
 * docs/api.md, qui autorise aussi les gestionnaires à supprimer).
 */
class MessagePolicy
{
    public function update(User $user, Message $message): bool
    {
        return $this->estAuteur($user, $message);
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->estAuteur($user, $message);
    }

    private function estAuteur(User $user, Message $message): bool
    {
        return $message->auteur_id !== null && $message->auteur_id === $user->id;
    }
}
