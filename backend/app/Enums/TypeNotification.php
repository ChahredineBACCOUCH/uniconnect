<?php

namespace App\Enums;

/**
 * Type d'une notification (champ `type` de la table `notifications`).
 *
 * Les valeurs sont reprises dans `docs/api.md` (section 8) : le frontend les
 * utilise pour choisir l'icône et le libellé de la notification.
 */
enum TypeNotification: string
{
    case INVITATION = 'INVITATION';
    case DEMANDE_ADHESION = 'DEMANDE_ADHESION';
    case ANNONCE = 'ANNONCE';
    case RESSOURCE = 'RESSOURCE';
    case SIGNALEMENT_TRAITE = 'SIGNALEMENT_TRAITE';
    case RETRAIT_ESPACE = 'RETRAIT_ESPACE';
}
