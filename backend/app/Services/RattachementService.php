<?php

namespace App\Services;

use App\Enums\EtatCompte;
use App\Enums\EtatEspace;
use App\Enums\Profil;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Models\Adhesion;
use App\Models\AnneeUniversitaire;
use App\Models\Espace;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * SCRUM-68 / SCRUM-82 : rattachement d'un utilisateur aux groupes
 * organisationnels auxquels il a droit, à l'activation de son compte.
 *
 * - Étudiant : groupe de sa classe + groupes des matières enseignées dans sa
 *   classe, avec le rôle MEMBRE.
 * - Enseignant responsable d'une matière : groupe de cette matière, avec le
 *   rôle PROPRIETAIRE (l'ancien propriétaire éventuel devient co-gestionnaire).
 *
 * Idempotent : chaque adhésion est créée avec `firstOrCreate` (unique
 * `espace_id + user_id`) et un espace n'a qu'un propriétaire.
 */
class RattachementService
{
    /**
     * @return int nombre d'adhésions réellement créées
     */
    public function rattacher(User $utilisateur): int
    {
        return match ($utilisateur->profil) {
            Profil::ETUDIANT => $this->rattacherEtudiant($utilisateur),
            Profil::ENSEIGNANT => $this->rattacherEnseignant($utilisateur),
            default => 0,
        };
    }

    /**
     * Groupe de classe + groupes de matière de la classe de l'étudiant.
     */
    private function rattacherEtudiant(User $utilisateur): int
    {
        $classe = $utilisateur->classe;

        if ($classe === null) {
            return 0;
        }

        $matiereIds = $classe->matieres()->pluck('matieres.id');

        $espaces = $this->espacesOrganisationnels($classe->annee_id)
            ->where(function ($query) use ($classe, $matiereIds): void {
                $query->where('classe_id', $classe->id);

                if ($matiereIds->isNotEmpty()) {
                    $query->orWhere(fn ($query) => $query
                        ->whereNull('classe_id')
                        ->whereIn('matiere_id', $matiereIds));
                }
            })
            ->get();

        $ajoutes = 0;

        foreach ($espaces as $espace) {
            $adhesion = Adhesion::firstOrCreate(
                ['espace_id' => $espace->id, 'user_id' => $utilisateur->id],
                ['role' => RoleMembre::MEMBRE],
            );

            if ($adhesion->wasRecentlyCreated) {
                $ajoutes++;
            }
        }

        return $ajoutes;
    }

    /**
     * Groupes des matières dont l'enseignant est le responsable.
     */
    private function rattacherEnseignant(User $utilisateur): int
    {
        $annee = AnneeUniversitaire::active()->first();

        if ($annee === null) {
            return 0;
        }

        $ajoutes = 0;

        $matieres = $utilisateur->matieres()
            ->where('matieres.actif', true)
            ->orderBy('matieres.code')
            ->get();

        foreach ($matieres as $matiere) {
            // Même règle que la génération : le premier enseignant actif de la
            // matière est le responsable (déterministe quand ils sont plusieurs).
            $responsable = $matiere->enseignants()
                ->where('users.profil', Profil::ENSEIGNANT->value)
                ->where('users.etat', EtatCompte::ACTIF->value)
                ->orderBy('users.id')
                ->first();

            if ($responsable === null || ! $responsable->is($utilisateur)) {
                continue;
            }

            $espaces = $this->espacesOrganisationnels($annee->id)
                ->whereNull('classe_id')
                ->where('matiere_id', $matiere->id)
                ->get();

            foreach ($espaces as $espace) {
                $ajoutes += $this->devenirProprietaire($espace, $utilisateur);
            }
        }

        return $ajoutes;
    }

    /**
     * Fait de l'utilisateur le propriétaire d'un espace, sans doublon et sans
     * casser l'unicité du propriétaire : l'ancien propriétaire éventuel reste
     * membre avec le rôle CO_GESTIONNAIRE.
     *
     * @return int 1 si une adhésion a été créée, 0 sinon
     */
    private function devenirProprietaire(Espace $espace, User $utilisateur): int
    {
        $adhesion = Adhesion::query()
            ->where('espace_id', $espace->id)
            ->where('user_id', $utilisateur->id)
            ->first();

        if ($adhesion?->role === RoleMembre::PROPRIETAIRE) {
            return 0;
        }

        // Libérer la place avant d'insérer ou de promouvoir : index unique
        // `un_proprietaire_par_espace` (PostgreSQL) sur les propriétaires.
        Adhesion::query()
            ->where('espace_id', $espace->id)
            ->where('role', RoleMembre::PROPRIETAIRE->value)
            ->where('user_id', '!=', $utilisateur->id)
            ->update(['role' => RoleMembre::CO_GESTIONNAIRE->value]);

        if ($adhesion === null) {
            Adhesion::create([
                'espace_id' => $espace->id,
                'user_id' => $utilisateur->id,
                'role' => RoleMembre::PROPRIETAIRE,
            ]);

            return 1;
        }

        $adhesion->update(['role' => RoleMembre::PROPRIETAIRE]);

        return 0;
    }

    /**
     * @return Builder<Espace>
     */
    private function espacesOrganisationnels(int $anneeId): Builder
    {
        return Espace::query()
            ->where('type', TypeEspace::ORGANISATIONNEL->value)
            ->where('annee_id', $anneeId)
            ->where('etat', EtatEspace::ACTIF->value);
    }
}
