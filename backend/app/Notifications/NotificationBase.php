<?php

namespace App\Notifications;

use App\Enums\TypeNotification;
use App\Models\Adhesion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Base commune des notifications de l'application (SCRUM-121).
 *
 * Toutes les notifications passent par le canal `database` (table
 * `notifications`) : le frontend les lit avec `GET /api/notifications`.
 *
 * Deux règles sont partagées par toutes les classes filles :
 * - `via()` ne renvoie `['database']` que si la personne a les notifications
 *   actives pour l'espace concerné (`adhesions.notifications_actives`). Une
 *   personne qui n'est pas encore membre de l'espace (cas d'une invitation)
 *   n'a pas encore ce réglage : on applique la valeur par défaut, « actif ».
 * - `toArray()` renvoie les quatre champs affichés dans l'application :
 *   `type`, `titre`, `texte` et `lien`.
 */
abstract class NotificationBase extends Notification
{
    use Queueable;

    /** Espace concerné : sert à vérifier `adhesions.notifications_actives`. */
    protected ?int $espaceId = null;

    /** Titre court affiché dans la liste des notifications. */
    protected string $titre = '';

    /** Texte de la notification (une ou deux phrases). */
    protected string $texte = '';

    /** Lien frontend à ouvrir au clic (route Vue). */
    protected string $lien = '';

    /** Type de la notification (champ `type` de la table `notifications`). */
    abstract public function type(): TypeNotification;

    /**
     * Canal de livraison : la base de données, et rien d'autre.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->notificationsActives($notifiable) ? ['database'] : [];
    }

    /**
     * Données affichées par le frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'titre' => $this->titre,
            'texte' => $this->texte,
            'lien' => $this->lien,
        ];
    }

    /** La personne a-t-elle les notifications actives pour cet espace ? */
    private function notificationsActives(object $notifiable): bool
    {
        // Notification sans espace (exemple : un signalement traité) : aucun
        // réglage à vérifier.
        if ($this->espaceId === null) {
            return true;
        }

        $adhesion = Adhesion::query()
            ->where('espace_id', $this->espaceId)
            ->where('user_id', $notifiable->getKey())
            ->first();

        // Pas encore adhérent (invitation) : le réglage par défaut est « actif ».
        return $adhesion === null || $adhesion->notifications_actives;
    }
}
