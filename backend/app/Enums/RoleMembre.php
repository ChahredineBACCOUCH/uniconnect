<?php

namespace App\Enums;

/**
 * Rôle d'un membre dans un espace (colonne `role` de `adhesions`).
 */
enum RoleMembre: string
{
    case PROPRIETAIRE = 'PROPRIETAIRE';
    case CO_GESTIONNAIRE = 'CO_GESTIONNAIRE';
    case MEMBRE = 'MEMBRE';

    public function libelle(): string
    {
        return match ($this) {
            self::PROPRIETAIRE => 'Propriétaire',
            self::CO_GESTIONNAIRE => 'Co-gestionnaire',
            self::MEMBRE => 'Membre',
        };
    }
}
