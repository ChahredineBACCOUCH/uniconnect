<?php

namespace App\Enums;

/**
 * Profil d'un utilisateur ou d'un compte autorisé (colonne `profil`).
 */
enum Profil: string
{
    case ETUDIANT = 'ETUDIANT';
    case ENSEIGNANT = 'ENSEIGNANT';
    case ADMINISTRATION = 'ADMINISTRATION';

    public function libelle(): string
    {
        return match ($this) {
            self::ETUDIANT => 'Étudiant',
            self::ENSEIGNANT => 'Enseignant',
            self::ADMINISTRATION => 'Administration',
        };
    }
}
