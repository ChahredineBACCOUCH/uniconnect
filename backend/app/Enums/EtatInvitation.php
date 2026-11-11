<?php

namespace App\Enums;

/**
 * État d'une invitation (colonne `etat` de `invitations`).
 */
enum EtatInvitation: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case ACCEPTEE = 'ACCEPTEE';
    case REFUSEE = 'REFUSEE';
    case EXPIREE = 'EXPIREE';
    case ANNULEE = 'ANNULEE';
}
