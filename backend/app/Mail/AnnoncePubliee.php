<?php

namespace App\Mail;

use App\Models\Annonce;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-123 : e-mail envoyé aux membres quand l'auteur demande à les prévenir.
 *
 * Le mailable est mis en file d'attente (`Mail::to(...)->queue(...)`) pour ne
 * pas ralentir la requête de publication.
 */
class AnnoncePubliee extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Annonce $annonce,
        public User $destinataire,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle annonce : '.$this->annonce->titre,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.annonce',
        );
    }
}
