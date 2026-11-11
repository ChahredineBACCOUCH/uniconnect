<?php

namespace App\Enums;

/**
 * État d'un espace (colonne `etat` de `espaces`).
 * La suppression est physique : il n'y a pas d'état SUPPRIME en base.
 */
enum EtatEspace: string
{
    case ACTIF = 'ACTIF';
    case ARCHIVE = 'ARCHIVE';

    public function libelle(): string
    {
        return match ($this) {
            self::ACTIF => 'Actif',
            self::ARCHIVE => 'Archivé',
        };
    }
}
