<?php

namespace App\Notifications;

use App\Enums\TypeNotification;
use App\Models\Espace;
use App\Models\User;

/**
 * SCRUM-121 : une personne a reçu une invitation dans un espace.
 *
 * Envoyée à l'invité, au moment où l'invitation est créée (SCRUM-88).
 */
class InvitationRecue extends NotificationBase
{
    public function __construct(private Espace $espace, private User $invitePar)
    {
        $this->espaceId = $espace->id;
        $this->titre = 'Invitation : '.$espace->nom;
        $this->texte = $invitePar->prenom.' '.$invitePar->nom
            .' vous a invité à rejoindre l\'espace « '.$espace->nom.' ».';
        // L'invité n'est pas encore membre de l'espace : la notification se
        // traite depuis la page des notifications.
        $this->lien = '/notifications';
    }

    public function type(): TypeNotification
    {
        return TypeNotification::INVITATION;
    }
}
