<?php

namespace App\Services;

use App\Models\AnneeUniversitaire;
use App\Models\CompteAutorise;
use App\Models\User;

/**
 * SCRUM-62 : vérifie qu'une personne a le droit de créer un compte
 * (elle doit figurer dans la liste des comptes autorisés de l'année active).
 */
class EligibiliteService
{
    /** Aucune année universitaire active : la plateforme est fermée. */
    public const PLATEFORME_FERMEE = 'PLATEFORME_FERMEE';

    /** Le couple matricule + e-mail ne figure pas dans comptes_autorises. */
    public const NON_AUTORISE = 'NON_AUTORISE';

    /** Un compte existe déjà pour ce matricule. */
    public const DEJA_INSCRIT = 'DEJA_INSCRIT';

    /**
     * Contrôle l'éligibilité dans cet ordre :
     * (a) année active ? sinon PLATEFORME_FERMEE ;
     * (b) couple matricule + e-mail présent dans comptes_autorises ? sinon NON_AUTORISE ;
     * (c) compte déjà créé pour ce matricule ? sinon DEJA_INSCRIT.
     *
     * @return string|null null si la personne est éligible, sinon le code de refus
     */
    public function verifier(string $matricule, string $email): ?string
    {
        if (! AnneeUniversitaire::active()->exists()) {
            return self::PLATEFORME_FERMEE;
        }

        $figureSurLaListe = CompteAutorise::query()
            ->where('matricule', $matricule)
            ->where('email', $email)
            ->exists();

        if (! $figureSurLaListe) {
            return self::NON_AUTORISE;
        }

        if (User::query()->where('matricule', $matricule)->exists()) {
            return self::DEJA_INSCRIT;
        }

        return null;
    }
}
