<?php

namespace App\Notifications;

use App\Enums\TypeNotification;
use App\Models\Espace;
use App\Models\User;

/**
 * SCRUM-121 : une nouvelle annonce a été publiée dans un espace (SCRUM-123).
 *
 * Envoyée aux membres de l'espace, à l'exception de son auteur.
 */
class NouvelleAnnonce extends NotificationBase
{
    public function __construct(private Espace $espace, private string $titreAnnonce, private User $auteur)
    {
        $this->espaceId = $espace->id;
        $this->titre = 'Annonce : '.$titreAnnonce;
        $this->texte = $auteur->prenom.' '.$auteur->nom
            .' a publié une annonce dans l\'espace « '.$espace->nom.' ».';
        $this->lien = '/espaces/'.$espace->id.'/annonces';
    }

    public function type(): TypeNotification
    {
        return TypeNotification::ANNONCE;
    }
}
