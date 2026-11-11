<?php

namespace App\Enums;

/**
 * Mode d'accès à un channel (colonne `mode_acces` de `espaces`).
 */
enum ModeAcces: string
{
    case OUVERT = 'OUVERT';
    case SUR_DEMANDE = 'SUR_DEMANDE';
    case RESTREINT = 'RESTREINT';

    public function libelle(): string
    {
        return match ($this) {
            self::OUVERT => 'Ouvert',
            self::SUR_DEMANDE => 'Sur demande',
            self::RESTREINT => 'Restreint',
        };
    }
}
