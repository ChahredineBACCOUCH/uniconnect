<?php

namespace App\Services;

use App\Enums\EtatCompte;
use App\Enums\EtatEspace;
use App\Enums\Profil;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Exceptions\AucuneAnneeActiveException;
use App\Models\Adhesion;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Espace;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SCRUM-82 : groupes organisationnels de l'année active.
 *
 * Un groupe par classe et un groupe par couple classe + matière, générés à
 * partir des référentiels. La génération est idempotente : elle s'appuie sur
 * l'index unique `espace_orga_unique` et sur `firstOrCreate`.
 */
class GenerationGroupesService
{
    /**
     * Aperçu de la génération, sans écrire en base.
     *
     * @return array{
     *     annee: array{id: int, libelle: string},
     *     total: int,
     *     a_creer: list<array<string, mixed>>,
     *     deja_existants: int,
     *     membres_a_ajouter: int
     * }
     */
    public function apercu(): array
    {
        $annee = $this->anneeActive();
        $existants = $this->espacesExistants($annee);

        $aCreer = [];
        $dejaExistants = 0;
        $membresAAjouter = 0;

        foreach ($this->plan($annee) as $groupe) {
            $espace = $existants->get($this->cle($groupe['classe']->id, $groupe['matiere']?->id));

            if ($espace === null) {
                $aCreer[] = $this->resume($groupe);
            } else {
                $dejaExistants++;
            }

            $membresAAjouter += $this->membresManquants($espace, $groupe['membres']);
        }

        return [
            'annee' => ['id' => $annee->id, 'libelle' => $annee->libelle],
            'total' => count($aCreer) + $dejaExistants,
            'a_creer' => $aCreer,
            'deja_existants' => $dejaExistants,
            'membres_a_ajouter' => $membresAAjouter,
        ];
    }

    /**
     * Crée les groupes manquants et complète leurs membres.
     *
     * @return array{
     *     annee: array{id: int, libelle: string},
     *     crees: int,
     *     deja_existants: int,
     *     membres_ajoutes: int
     * }
     *
     * @throws AucuneAnneeActiveException
     */
    public function generer(?User $auteur = null): array
    {
        $annee = $this->anneeActive();

        return DB::transaction(function () use ($annee, $auteur): array {
            $crees = 0;
            $dejaExistants = 0;
            $membresAjoutes = 0;

            foreach ($this->plan($annee, $auteur) as $groupe) {
                $espace = Espace::firstOrCreate(
                    [
                        'type' => TypeEspace::ORGANISATIONNEL->value,
                        'annee_id' => $annee->id,
                        'classe_id' => $groupe['classe']->id,
                        'matiere_id' => $groupe['matiere']?->id,
                    ],
                    [
                        'nom' => $groupe['nom'],
                        'description' => $groupe['description'],
                        'etat' => EtatEspace::ACTIF->value,
                        'created_by' => $auteur?->id,
                    ],
                );

                if ($espace->wasRecentlyCreated) {
                    $crees++;
                } else {
                    $dejaExistants++;
                }

                $membresAjoutes += $this->ajouterMembres(
                    $espace,
                    $groupe['proprietaire'],
                    $groupe['etudiants'],
                );
            }

            return [
                'annee' => ['id' => $annee->id, 'libelle' => $annee->libelle],
                'crees' => $crees,
                'deja_existants' => $dejaExistants,
                'membres_ajoutes' => $membresAjoutes,
            ];
        });
    }

    /**
     * Groupes attendus : un par classe, puis un par matière de chaque classe.
     *
     * @return list<array{
     *     classe: Classe,
     *     matiere: ?Matiere,
     *     nom: string,
     *     description: string,
     *     proprietaire: ?User,
     *     etudiants: Collection<int, User>,
     *     membres: Collection<int, User>
     * }>
     */
    private function plan(AnneeUniversitaire $annee, ?User $auteur = null): array
    {
        // Les groupes de classe appartiennent à l'administration ; ceux de
        // matière à l'enseignant responsable (repli sur l'administration).
        $proprietaireDefaut = $this->administrateurParDefaut() ?? $auteur;

        $classes = Classe::query()
            ->where('annee_id', $annee->id)
            ->where('actif', true)
            ->with([
                'matieres' => fn ($query) => $query
                    ->where('matieres.actif', true)
                    ->orderBy('matieres.code'),
                'etudiants' => fn ($query) => $query
                    ->where('profil', Profil::ETUDIANT->value)
                    ->where('etat', EtatCompte::ACTIF->value)
                    ->orderBy('users.nom')
                    ->orderBy('users.prenom'),
            ])
            ->orderBy('code')
            ->get();

        $plan = [];

        foreach ($classes as $classe) {
            $plan[] = [
                'classe' => $classe,
                'matiere' => null,
                'nom' => Str::limit($classe->libelle, 120, ''),
                'description' => "Groupe de classe {$classe->libelle} ({$annee->libelle}).",
                'proprietaire' => $proprietaireDefaut,
                'etudiants' => $classe->etudiants,
                'membres' => $this->membres($proprietaireDefaut, $classe->etudiants),
            ];

            foreach ($classe->matieres as $matiere) {
                $proprietaire = $this->enseignantResponsable($matiere) ?? $proprietaireDefaut;

                $plan[] = [
                    'classe' => $classe,
                    'matiere' => $matiere,
                    'nom' => Str::limit("{$matiere->libelle} · {$classe->libelle}", 120, ''),
                    'description' => "Groupe de la matière {$matiere->libelle} pour la classe {$classe->libelle} ({$annee->libelle}).",
                    'proprietaire' => $proprietaire,
                    'etudiants' => $classe->etudiants,
                    'membres' => $this->membres($proprietaire, $classe->etudiants),
                ];
            }
        }

        return $plan;
    }

    /**
     * Ajoute le propriétaire et les étudiants d'un groupe, sans doublon.
     *
     * @param  Collection<int, User>  $etudiants
     * @return int nombre d'adhésions réellement créées
     */
    private function ajouterMembres(Espace $espace, ?User $proprietaire, Collection $etudiants): int
    {
        // Un groupe archivé ne doit plus recevoir de membres.
        if ($espace->etat !== EtatEspace::ACTIF) {
            return 0;
        }

        $ajoutes = 0;

        if ($proprietaire !== null && ! $this->aUnProprietaire($espace)) {
            $adhesion = Adhesion::firstOrCreate(
                ['espace_id' => $espace->id, 'user_id' => $proprietaire->id],
                ['role' => RoleMembre::PROPRIETAIRE],
            );

            if ($adhesion->wasRecentlyCreated) {
                $ajoutes++;
            } elseif ($adhesion->role !== RoleMembre::PROPRIETAIRE) {
                $adhesion->update(['role' => RoleMembre::PROPRIETAIRE]);
            }
        }

        foreach ($etudiants as $etudiant) {
            if ($proprietaire !== null && $etudiant->is($proprietaire)) {
                continue;
            }

            $adhesion = Adhesion::firstOrCreate(
                ['espace_id' => $espace->id, 'user_id' => $etudiant->id],
                ['role' => RoleMembre::MEMBRE],
            );

            if ($adhesion->wasRecentlyCreated) {
                $ajoutes++;
            }
        }

        return $ajoutes;
    }

    /**
     * L'enseignant responsable d'une matière (le premier inscrit, pour rester
     * déterministe quand plusieurs enseignants la partagent).
     */
    private function enseignantResponsable(Matiere $matiere): ?User
    {
        return $matiere->enseignants()
            ->where('users.profil', Profil::ENSEIGNANT->value)
            ->where('users.etat', EtatCompte::ACTIF->value)
            ->orderBy('users.id')
            ->first();
    }

    private function administrateurParDefaut(): ?User
    {
        return User::query()
            ->where('profil', Profil::ADMINISTRATION->value)
            ->where('etat', EtatCompte::ACTIF->value)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  Collection<int, User>  $etudiants
     * @return Collection<int, User>
     */
    private function membres(?User $proprietaire, Collection $etudiants): Collection
    {
        // `concat` crée une nouvelle collection : la liste des étudiants de la
        // classe est réutilisée par plusieurs groupes du plan et ne doit pas
        // être modifiée en place.
        return $etudiants
            ->concat($proprietaire !== null ? [$proprietaire] : [])
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, User>  $membres
     */
    private function membresManquants(?Espace $espace, Collection $membres): int
    {
        if ($membres->isEmpty() || ($espace !== null && $espace->etat !== EtatEspace::ACTIF)) {
            return 0;
        }

        $ids = $membres->modelKeys();

        if ($espace === null) {
            return count($ids);
        }

        $existants = $espace->adhesions()
            ->whereIn('user_id', $ids)
            ->pluck('user_id')
            ->all();

        return count(array_diff($ids, $existants));
    }

    /**
     * @return Collection<string, Espace>
     */
    private function espacesExistants(AnneeUniversitaire $annee): Collection
    {
        return Espace::query()
            ->where('type', TypeEspace::ORGANISATIONNEL->value)
            ->where('annee_id', $annee->id)
            ->get()
            ->keyBy(fn (Espace $espace) => $this->cle($espace->classe_id, $espace->matiere_id));
    }

    private function aUnProprietaire(Espace $espace): bool
    {
        return $espace->adhesions()
            ->where('role', RoleMembre::PROPRIETAIRE->value)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $groupe
     * @return array<string, mixed>
     */
    private function resume(array $groupe): array
    {
        $proprietaire = $groupe['proprietaire'];

        return [
            'nom' => $groupe['nom'],
            'type' => $groupe['matiere'] === null ? 'CLASSE' : 'MATIERE',
            'classe' => $groupe['classe']->code,
            'matiere' => $groupe['matiere']?->code,
            'proprietaire' => $proprietaire === null
                ? null
                : trim($proprietaire->prenom.' '.$proprietaire->nom),
        ];
    }

    private function cle(int $classeId, ?int $matiereId): string
    {
        return $classeId.'-'.($matiereId ?? 0);
    }

    /**
     * @throws AucuneAnneeActiveException
     */
    private function anneeActive(): AnneeUniversitaire
    {
        return AnneeUniversitaire::active()->first() ?? throw new AucuneAnneeActiveException;
    }
}
