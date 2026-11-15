<?php

namespace App\Notifications;

use App\Enums\TypeNotification;
use App\Models\Espace;
use App\Models\User;

/**
 * SCRUM-121 : la personne a été retirée d'un espace (SCRUM-90).
 *
 * Envoyée au membre retiré, juste après la suppression de son adhésion.
 */
class RetraitEspace extends NotificationBase
{
    public function __construct(private Espace $espace, private User $retirePar)
    {
        $this->espaceId = $espace->id;
        $this->titre = 'Vous avez été retiré de : '.$espace->nom;
        $this->texte = $retirePar->prenom.' '.$retirePar->nom
            .' vous a retiré de l\'espace « '.$espace->nom.' ».';
        // La personne n'a plus accès à l'espace : on la ramène vers ses espaces.
        $this->lien = '/espaces';
    }

    public function type(): TypeNotification
    {
        return TypeNotification::RETRAIT_ESPACE;
    }
}
