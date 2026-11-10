<?php

namespace App\Policies;

use App\Enums\EtatEspace;
use App\Models\Message;
use App\Models\User;

/**
 * SCRUM-94 : un message n'appartient qu'à son auteur.
 * Seul l'auteur peut le modifier ou le supprimer (le ticket prime sur
 * docs/api.md, qui autorise aussi les gestionnaires à supprimer).
 *
 * L'auteur doit en plus être **encore membre** de l'espace (un membre retiré
 * perd l'accès aux messages, US-20) et l'espace ne doit pas être archivé
 * (un espace archivé est en lecture seule, comme pour l'envoi).
 */
class MessagePolicy
{
    public function update(User $user, Message $message): bool
    {
        return $this->peutAgirSurSonMessage($user, $message);
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->peutAgirSurSonMessage($user, $message);
    }

    private function peutAgirSurSonMessage(User $user, Message $message): bool
    {
        if ($message->auteur_id === null || $message->auteur_id !== $user->id) {
            return false;
        }

        $espace = $message->espace;

        return $espace->etat !== EtatEspace::ARCHIVE
            && $espace->adhesions()->where('user_id', $user->id)->exists();
    }
}
