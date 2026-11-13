<?php

namespace App\Notifications;

use App\Enums\TypeNotification;
use App\Models\Espace;

/**
 * SCRUM-121 : un signalement a été traité par la modération.
 *
 * Envoyée à la personne qui a signalé le message (SCRUM-119 / modération).
 */
class SignalementTraite extends NotificationBase
{
    public function __construct(private Espace $espace, private string $decision)
    {
        $this->espaceId = $espace->id;
        $this->titre = 'Signalement traité : '.$espace->nom;
        $this->texte = 'Votre signalement dans l\'espace « '.$espace->nom.' » a été traité : '.$decision;
        $this->lien = '/espaces/'.$espace->id;
    }

    public function type(): TypeNotification
    {
        return TypeNotification::SIGNALEMENT_TRAITE;
    }
}
