<?php

namespace App\Enums;

/**
 * État d'un compte (colonne `etat` de `users` et `comptes_autorises`).
 */
enum EtatCompte: string
{
    case NON_CREE = 'NON_CREE';
    case EN_ATTENTE = 'EN_ATTENTE';
    case ACTIF = 'ACTIF';
    case DESACTIVE = 'DESACTIVE';
    case SUSPENDU = 'SUSPENDU';

    public function libelle(): string
    {
        return match ($this) {
            self::NON_CREE => 'Non créé',
            self::EN_ATTENTE => 'En attente d\'activation',
            self::ACTIF => 'Actif',
            self::DESACTIVE => 'Désactivé',
            self::SUSPENDU => 'Suspendu',
        };
    }
}
