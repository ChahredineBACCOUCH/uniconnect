<?php

namespace App\Policies;

use App\Models\Espace;
use App\Models\Message;
use App\Models\PieceJointe;
use App\Models\User;

/**
 * SCRUM-137 : un fichier n'est téléchargeable que par un membre actuel de
 * l'espace qui le contient.
 *
 * L'autorisation est vérifiée à chaque requête : un membre retiré perd l'accès
 * immédiatement (SCRUM-92) et un fichier dont le message est masqué ou expiré
 * n'est plus servi, même avec un lien direct.
 */
class FichierPolicy
{
    public function view(User $user, PieceJointe $fichier): bool
    {
        $espace = $this->espace($fichier);

        if ($espace === null) {
            return false;
        }

        // Adhésion contrôlée en base, jamais depuis le jeton : le retrait prend
        // effet dès la requête suivante.
        if (! $espace->adhesions()->where('user_id', $user->id)->exists()) {
            return false;
        }

        if ($fichier->expire_le !== null && $fichier->expire_le->isPast()) {
            return false;
        }

        $message = $fichier->message;

        if ($message instanceof Message) {
            return ! $message->masque
                && ($message->expire_le === null || $message->expire_le->isFuture());
        }

        // Pièce jointe d'une annonce : l'annonce ne peut être ni masquée ni
        // expirée, seule l'expiration du fichier lui-même s'applique.
        return $fichier->annonce !== null;
    }

    private function espace(PieceJointe $fichier): ?Espace
    {
        $message = $fichier->message;

        if ($message instanceof Message) {
            return $message->espace;
        }

        return $fichier->annonce?->espace;
    }
}
