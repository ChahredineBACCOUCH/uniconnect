<?php

namespace App\Enums;

/**
 * Droit individuel accordé à un utilisateur (table `droits_utilisateur`).
 */
enum Droit: string
{
    case GERER_PARAMETRAGE = 'GERER_PARAMETRAGE';
    case GERER_UTILISATEURS = 'GERER_UTILISATEURS';
    case CREER_GROUPE_ORGA = 'CREER_GROUPE_ORGA';
    case CREER_GROUPE_PRIVE = 'CREER_GROUPE_PRIVE';
    case CREER_CHANNEL = 'CREER_CHANNEL';
    case MODERER = 'MODERER';
    case CONSULTER_TABLEAU_BORD = 'CONSULTER_TABLEAU_BORD';

    public function libelle(): string
    {
        return match ($this) {
            self::GERER_PARAMETRAGE => 'Gérer le paramétrage',
            self::GERER_UTILISATEURS => 'Gérer les utilisateurs',
            self::CREER_GROUPE_ORGA => 'Créer des groupes organisationnels',
            self::CREER_GROUPE_PRIVE => 'Créer des groupes privés',
            self::CREER_CHANNEL => 'Créer des channels',
            self::MODERER => 'Modérer',
            self::CONSULTER_TABLEAU_BORD => 'Consulter le tableau de bord',
        };
    }
}
