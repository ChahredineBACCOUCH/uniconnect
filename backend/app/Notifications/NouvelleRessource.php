<?php

namespace App\Notifications;

use App\Enums\TypeNotification;
use App\Models\Espace;
use App\Models\Ressource;
use App\Models\User;

/**
 * SCRUM-121 : une nouvelle ressource pédagogique a été déposée dans un espace
 * (SCRUM-126).
 *
 * Envoyée aux membres de l'espace, à l'exception de son auteur.
 */
class NouvelleRessource extends NotificationBase
{
    public function __construct(private Espace $espace, private Ressource $ressource, private User $auteur)
    {
        $this->espaceId = $espace->id;
        $this->titre = 'Nouvelle ressource : '.$ressource->titre;
        $this->texte = $auteur->prenom.' '.$auteur->nom
            .' a ajouté la ressource « '.$ressource->titre.' » dans l\'espace « '.$espace->nom.' ».';
        $this->lien = '/espaces/'.$espace->id.'/ressources';
    }

    public function type(): TypeNotification
    {
        return TypeNotification::RESSOURCE;
    }
}
