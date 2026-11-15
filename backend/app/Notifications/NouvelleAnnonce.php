<?php

namespace App\Notifications;

use App\Enums\TypeNotification;
use App\Models\Annonce;
use Illuminate\Support\Str;

/**
 * SCRUM-123 : notification applicative envoyée à tous les membres lorsqu'une
 * annonce est publiée dans leur espace.
 *
 * SCRUM-121 : respecte le réglage `adhesions.notifications_actives` de chaque
 * membre (via `NotificationBase`).
 */
class NouvelleAnnonce extends NotificationBase
{
    public function __construct(public Annonce $annonce)
    {
        $this->espaceId = $annonce->espace_id;
        $this->titre = $annonce->titre;
        $this->texte = Str::limit($annonce->contenu, 140);
        $this->lien = '/espaces/'.$annonce->espace_id.'/annonces';
    }

    public function type(): TypeNotification
    {
        return TypeNotification::ANNONCE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'annonce_id' => $this->annonce->id,
            'espace_id' => $this->annonce->espace_id,
            'espace_nom' => $this->annonce->espace?->nom,
        ]);
    }
}
