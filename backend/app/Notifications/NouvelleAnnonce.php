<?php

namespace App\Notifications;

use App\Models\Annonce;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * SCRUM-123 : notification applicative envoyée à tous les membres lorsqu'une
 * annonce est publiée dans leur espace.
 */
class NouvelleAnnonce extends Notification
{
    use Queueable;

    public function __construct(public Annonce $annonce) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ANNONCE',
            'annonce_id' => $this->annonce->id,
            'espace_id' => $this->annonce->espace_id,
            'espace_nom' => $this->annonce->espace?->nom,
            'titre' => $this->annonce->titre,
            'texte' => Str::limit($this->annonce->contenu, 140),
            'lien' => '/espaces/'.$this->annonce->espace_id.'/annonces',
        ];
    }
}
