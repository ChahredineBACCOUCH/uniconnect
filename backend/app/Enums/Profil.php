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

    /**
     * Droits accordés à l'activation du compte (maquette A04 : « droits par défaut
     * du profil »). Les autres droits sont donnés ensuite par l'administration.
     *
     * @return list<Droit>
     */
    public function droitsParDefaut(): array
    {
        return match ($this) {
            self::ETUDIANT, self::ENSEIGNANT => [Droit::CREER_GROUPE_PRIVE],
            self::ADMINISTRATION => [],
        };
    }
}
