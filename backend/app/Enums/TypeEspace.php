<?php

namespace App\Enums;

/**
 * Type d'un espace (colonne `type` de `espaces`).
 */
enum TypeEspace: string
{
    case ORGANISATIONNEL = 'ORGANISATIONNEL';
    case PRIVE = 'PRIVE';
    case CHANNEL = 'CHANNEL';

    public function libelle(): string
    {
        return match ($this) {
            self::ORGANISATIONNEL => 'Groupe organisationnel',
            self::PRIVE => 'Groupe privé',
            self::CHANNEL => 'Channel',
        };
    }
}
