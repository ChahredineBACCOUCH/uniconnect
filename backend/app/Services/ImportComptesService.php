<?php

namespace App\Services;

use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Imports\LignesImport;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\CompteAutorise;
use App\Models\ImportCompte;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * SCRUM-76 : import des comptes autorisés en deux temps.
 *
 * `apercu()` lit le fichier Excel et contrôle chaque ligne sans rien écrire.
 * `valider()` enregistre les lignes valides transmises par l'administration et
 * conserve un historique de l'opération.
 */
class ImportComptesService
{
    /** Colonnes attendues dans le fichier, dans cet ordre. */
    public const COLONNES = ['matricule', 'nom', 'prenom', 'email', 'profil', 'classe_code'];

    /** Profils acceptés par la table `comptes_autorises`. */
    private const PROFILS = ['ETUDIANT', 'ENSEIGNANT', 'ADMINISTRATION'];

    /** Garde-fou : un import manuel ne dépasse pas cette taille. */
    private const MAX_LIGNES = 2000;

    /**
     * Aperçu d'un fichier : lignes valides et erreurs, sans aucune écriture.
     *
     * @return array{
     *     total: int,
     *     valides: int,
     *     erreurs: array<int, array{ligne: int, motif: string}>,
     *     lignes: array<int, array<string, mixed>>
     * }
     */
    public function apercu(UploadedFile $fichier): array
    {
        return $this->analyser($this->lire($fichier));
    }

    /**
     * Enregistre les lignes validées lors de l'aperçu.
     *
     * Les lignes invalides ou devenues des doublons entre-temps sont ignorées
     * et comptées dans `ignores`.
     *
     * @param  array<int, array<string, mixed>>  $lignes
     * @return array{
     *     importes: int,
     *     ignores: int,
     *     erreurs: array<int, array{ligne: int, motif: string}>,
     *     import_id: int
     * }
     */
    public function valider(array $lignes, ?string $fichier = null): array
    {
        $normalisees = [];

        foreach (array_values($lignes) as $index => $ligne) {
            $normalisees[] = [
                'ligne' => (int) ($ligne['ligne'] ?? $index + 2),
                ...$this->normaliserLigne($ligne),
            ];
        }

        $resultat = $this->analyser($normalisees);

        $import = DB::transaction(function () use ($resultat, $fichier): ImportCompte {
            foreach ($resultat['lignes'] as $ligne) {
                CompteAutorise::create([
                    'matricule' => $ligne['matricule'],
                    'nom' => $ligne['nom'],
                    'prenom' => $ligne['prenom'],
                    'email' => $ligne['email'],
                    'profil' => Profil::from($ligne['profil']),
                    'classe_id' => $ligne['classe_id'],
                    'etat' => EtatCompte::NON_CREE,
                ]);
            }

            return ImportCompte::create([
                'user_id' => Auth::id(),
                'fichier' => $fichier,
                'total_lignes' => $resultat['total'],
                'importes' => $resultat['valides'],
                'ignores' => $resultat['total'] - $resultat['valides'],
                'erreurs' => $resultat['erreurs'],
            ]);
        });

        return [
            'importes' => $resultat['valides'],
            'ignores' => $resultat['total'] - $resultat['valides'],
            'erreurs' => $resultat['erreurs'],
            'import_id' => $import->id,
        ];
    }

    /**
     * Lit la première feuille du classeur et normalise les lignes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lire(UploadedFile $fichier): array
    {
        try {
            $feuilles = Excel::toArray(new LignesImport, $fichier);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'fichier' => "Le fichier n'a pas pu être lu. Utilisez le modèle Excel fourni.",
            ]);
        }

        $brutes = $feuilles[0] ?? [];

        if ($brutes === []) {
            throw ValidationException::withMessages([
                'fichier' => 'Le fichier est vide.',
            ]);
        }

        $entetes = array_map(
            fn (mixed $valeur): string => $this->normaliserEntete($valeur),
            array_shift($brutes),
        );

        $positions = [];

        foreach (self::COLONNES as $colonne) {
            $position = array_search($colonne, $entetes, true);

            if ($position === false) {
                throw ValidationException::withMessages([
                    'fichier' => 'Le fichier doit contenir les colonnes : '.implode(', ', self::COLONNES).'.',
                ]);
            }

            $positions[$colonne] = $position;
        }

        $lignes = [];

        foreach ($brutes as $decalage => $brute) {
            $ligne = ['ligne' => $decalage + 2];

            foreach ($positions as $colonne => $position) {
                $ligne[$colonne] = $this->texte($brute[$position] ?? null);
            }

            // Une ligne entièrement vide (fin de feuille) est ignorée.
            if (implode('', Arr::only($ligne, self::COLONNES)) === '') {
                continue;
            }

            $lignes[] = $ligne;
        }

        if ($lignes === []) {
            throw ValidationException::withMessages([
                'fichier' => 'Le fichier ne contient aucune ligne à importer.',
            ]);
        }

        if (count($lignes) > self::MAX_LIGNES) {
            throw ValidationException::withMessages([
                'fichier' => 'Le fichier ne peut pas contenir plus de '.self::MAX_LIGNES.' lignes.',
            ]);
        }

        return $lignes;
    }

    /**
     * Contrôle chaque ligne et résout la classe des étudiants.
     *
     * @param  array<int, array<string, mixed>>  $lignes
     * @return array{
     *     total: int,
     *     valides: int,
     *     erreurs: array<int, array{ligne: int, motif: string}>,
     *     lignes: array<int, array<string, mixed>>
     * }
     */
    private function analyser(array $lignes): array
    {
        $annee = AnneeUniversitaire::query()->active()->first();

        /** @var array<string, int> $classes */
        $classes = $annee === null
            ? []
            : Classe::query()->where('annee_id', $annee->id)->pluck('id', 'code')->all();

        /** @var array<string, bool> $matriculesExistants */
        $matriculesExistants = array_flip(CompteAutorise::query()
            ->whereIn('matricule', array_column($lignes, 'matricule'))
            ->pluck('matricule')
            ->all());

        /** @var array<string, bool> $emailsExistants */
        $emailsExistants = array_flip(CompteAutorise::query()
            ->whereIn('email', array_column($lignes, 'email'))
            ->pluck('email')
            ->all());

        $matriculesVus = [];
        $emailsVus = [];
        $erreurs = [];
        $valides = [];

        foreach ($lignes as $ligne) {
            $motifs = [];
            $matricule = $ligne['matricule'];
            $email = $ligne['email'];
            $profil = mb_strtoupper($ligne['profil']);
            $classeCode = $ligne['classe_code'];
            $classeId = null;

            if ($matricule === '') {
                $motifs[] = 'Le matricule est obligatoire.';
            } elseif (mb_strlen($matricule) > 30) {
                $motifs[] = 'Le matricule ne peut pas dépasser 30 caractères.';
            } elseif (isset($matriculesVus[$matricule])) {
                $motifs[] = 'Ce matricule figure plusieurs fois dans le fichier.';
            } elseif (isset($matriculesExistants[$matricule])) {
                $motifs[] = 'Ce matricule figure déjà dans la liste des comptes autorisés.';
            } else {
                $matriculesVus[$matricule] = true;
            }

            if ($ligne['nom'] === '') {
                $motifs[] = 'Le nom est obligatoire.';
            } elseif (mb_strlen($ligne['nom']) > 80) {
                $motifs[] = 'Le nom ne peut pas dépasser 80 caractères.';
            }

            if ($ligne['prenom'] === '') {
                $motifs[] = 'Le prénom est obligatoire.';
            } elseif (mb_strlen($ligne['prenom']) > 80) {
                $motifs[] = 'Le prénom ne peut pas dépasser 80 caractères.';
            }

            if ($email === '') {
                $motifs[] = "L'adresse e-mail est obligatoire.";
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $motifs[] = "L'adresse e-mail est invalide.";
            } elseif (mb_strlen($email) > 190) {
                $motifs[] = "L'adresse e-mail ne peut pas dépasser 190 caractères.";
            } elseif (isset($emailsVus[$email])) {
                $motifs[] = 'Cette adresse e-mail figure plusieurs fois dans le fichier.';
            } elseif (isset($emailsExistants[$email])) {
                $motifs[] = 'Cette adresse e-mail figure déjà dans la liste des comptes autorisés.';
            } else {
                $emailsVus[$email] = true;
            }

            if (! in_array($profil, self::PROFILS, true)) {
                $motifs[] = 'Le profil doit être ETUDIANT, ENSEIGNANT ou ADMINISTRATION.';
            } elseif ($profil === Profil::ETUDIANT->value) {
                if ($classeCode === '') {
                    $motifs[] = 'La classe est obligatoire pour un étudiant.';
                } elseif (! isset($classes[$classeCode])) {
                    $motifs[] = $annee === null
                        ? "Aucune année universitaire active : impossible de vérifier la classe « {$classeCode} »."
                        : "La classe « {$classeCode} » n'existe pas pour l'année universitaire active.";
                } else {
                    $classeId = $classes[$classeCode];
                }
            } elseif ($classeCode !== '') {
                $motifs[] = 'La classe ne doit être renseignée que pour un étudiant.';
            }

            if ($motifs !== []) {
                $erreurs[] = [
                    'ligne' => $ligne['ligne'],
                    'motif' => implode(' ', $motifs),
                ];

                continue;
            }

            $valides[] = [
                'ligne' => $ligne['ligne'],
                'matricule' => $matricule,
                'nom' => $ligne['nom'],
                'prenom' => $ligne['prenom'],
                'email' => $email,
                'profil' => $profil,
                'classe_code' => $classeCode,
                'classe_id' => $classeId,
            ];
        }

        return [
            'total' => count($lignes),
            'valides' => count($valides),
            'erreurs' => $erreurs,
            'lignes' => $valides,
        ];
    }

    /**
     * @param  array<string, mixed>  $ligne
     * @return array<string, string>
     */
    private function normaliserLigne(array $ligne): array
    {
        $normalisee = [];

        foreach (self::COLONNES as $colonne) {
            $normalisee[$colonne] = $this->texte($ligne[$colonne] ?? null);
        }

        return $normalisee;
    }

    private function normaliserEntete(mixed $valeur): string
    {
        $entete = mb_strtolower($this->texte($valeur));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $entete), '_');
    }

    private function texte(mixed $valeur): string
    {
        if ($valeur === null) {
            return '';
        }

        if (is_bool($valeur)) {
            return $valeur ? '1' : '0';
        }

        return trim((string) $valeur);
    }
}
