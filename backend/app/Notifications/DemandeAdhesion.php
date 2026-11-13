<?php

namespace App\Notifications;

use App\Enums\TypeNotification;
use App\Models\Espace;
use App\Models\User;

/**
 * SCRUM-121 : une personne demande à rejoindre un espace (SCRUM-119).
 *
 * Envoyée aux gestionnaires de l'espace (propriétaire et co-gestionnaires),
 * qui décident d'accepter ou de refuser la demande.
 */
class DemandeAdhesion extends NotificationBase
{
    public function __construct(private Espace $espace, private User $demandeur)
    {
        $this->espaceId = $espace->id;
        $this->titre = 'Demande d\'adhésion : '.$espace->nom;
        $this->texte = $demandeur->prenom.' '.$demandeur->nom
            .' demande à rejoindre l\'espace « '.$espace->nom.' ».';
        $this->lien = '/espaces/'.$espace->id.'/membres';
    }

    public function type(): TypeNotification
    {
        return TypeNotification::DEMANDE_ADHESION;
    }
}
