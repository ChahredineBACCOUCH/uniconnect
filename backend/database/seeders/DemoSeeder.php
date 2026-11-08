<?php

namespace Database\Seeders;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\CompteAutorise;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration : référentiel académique, comptes autorisés et
 * trois comptes déjà activés pour tester l'application.
 *
 * À lancer avec : php artisan migrate:fresh --seed
 */
class DemoSeeder extends Seeder
{
    private const MOT_DE_PASSE = 'Demo1234!';

    private const NB_ETUDIANTS = 35;

    private const NB_ENSEIGNANTS = 4;

    // Noms fixes plutôt que Faker : Faker est une dépendance de développement
    // (require-dev), absente en production (composer install --no-dev). Le seeder
    // doit pouvoir tourner sur Render pour la démo, et les données restent identiques
    // d'un lancement à l'autre.
    private const PRENOMS = [
        'Yasmine', 'Mehdi', 'Salma', 'Karim', 'Ines', 'Omar', 'Rania', 'Youssef',
        'Nour', 'Hamza', 'Lina', 'Adam', 'Mariem', 'Aziz', 'Sarra', 'Bilel',
    ];

    private const NOMS = [
        'Trabelsi', 'Ben Salah', 'Gharbi', 'Mansouri', 'Jebali', 'Hammami', 'Chaouch',
        'Ayari', 'Bouazizi', 'Khelifi', 'Ferchichi', 'Saidi', 'Riahi',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $annee = $this->creerAnnee();
            $departement = $this->creerDepartement();
            $filieres = $this->creerFilieres($departement);
            $niveaux = $this->creerNiveaux();
            $classes = $this->creerClasses($annee, $filieres, $niveaux);

            $this->creerMatieres($classes);
            $this->creerComptesAutorises($classes);
            $this->creerComptesDemo($classes);
        });
    }

    private function creerAnnee(): AnneeUniversitaire
    {
        // Une seule année peut être active : on désactive les précédentes.
        AnneeUniversitaire::query()->update(['active' => false]);

        return AnneeUniversitaire::updateOrCreate(
            ['libelle' => '2026/2027'],
            [
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-06-30',
                'active' => true,
            ],
        );
    }

    private function creerDepartement(): Departement
    {
        return Departement::updateOrCreate(
            ['code' => 'INFO'],
            ['libelle' => 'Informatique', 'actif' => true],
        );
    }

    /**
     * @return array<string, Filiere>
     */
    private function creerFilieres(Departement $departement): array
    {
        $definitions = [
            'GL' => 'Génie Logiciel',
            'SI' => 'Systèmes d\'Information',
        ];

        $filieres = [];

        foreach ($definitions as $code => $libelle) {
            $filieres[$code] = Filiere::updateOrCreate(
                ['code' => $code],
                [
                    'libelle' => $libelle,
                    'departement_id' => $departement->id,
                    'actif' => true,
                ],
            );
        }

        return $filieres;
    }

    /**
     * @return array<string, Niveau>
     */
    private function creerNiveaux(): array
    {
        $definitions = [
            'L2' => 'Licence 2',
            'L3' => 'Licence 3',
        ];

        $niveaux = [];

        foreach ($definitions as $code => $libelle) {
            $niveaux[$code] = Niveau::updateOrCreate(
                ['code' => $code],
                ['libelle' => $libelle],
            );
        }

        return $niveaux;
    }

    /**
     * @param  array<string, Filiere>  $filieres
     * @param  array<string, Niveau>  $niveaux
     * @return array<string, Classe>
     */
    private function creerClasses(AnneeUniversitaire $annee, array $filieres, array $niveaux): array
    {
        $definitions = [
            'L2-INFO-A' => ['libelle' => 'L2 Informatique A', 'filiere' => 'GL', 'niveau' => 'L2'],
            'L2-INFO-B' => ['libelle' => 'L2 Informatique B', 'filiere' => 'SI', 'niveau' => 'L2'],
            'L3-INFO' => ['libelle' => 'L3 Informatique', 'filiere' => 'GL', 'niveau' => 'L3'],
        ];

        $classes = [];

        foreach ($definitions as $code => $definition) {
            $classes[$code] = Classe::updateOrCreate(
                ['code' => $code, 'annee_id' => $annee->id],
                [
                    'libelle' => $definition['libelle'],
                    'filiere_id' => $filieres[$definition['filiere']]->id,
                    'niveau_id' => $niveaux[$definition['niveau']]->id,
                    'actif' => true,
                ],
            );
        }

        return $classes;
    }

    /**
     * Crée 6 matières et les relie aux trois classes.
     *
     * @param  array<string, Classe>  $classes
     */
    private function creerMatieres(array $classes): void
    {
        $definitions = [
            'INF201' => ['libelle' => 'Algorithmique avancée', 'semestre' => 1],
            'INF202' => ['libelle' => 'Bases de données', 'semestre' => 1],
            'INF203' => ['libelle' => 'Développement web', 'semestre' => 2],
            'INF204' => ['libelle' => 'Réseaux', 'semestre' => 1],
            'INF205' => ['libelle' => 'Génie logiciel', 'semestre' => 2],
            'INF206' => ['libelle' => 'Systèmes d\'exploitation', 'semestre' => 2],
        ];

        $matieres = [];

        foreach ($definitions as $code => $definition) {
            $matieres[] = Matiere::updateOrCreate(
                ['code' => $code],
                [
                    'libelle' => $definition['libelle'],
                    'semestre' => $definition['semestre'],
                    'actif' => true,
                ],
            );
        }

        $matiereIds = array_map(fn (Matiere $matiere) => $matiere->id, $matieres);

        foreach ($classes as $classe) {
            $classe->matieres()->sync($matiereIds);
        }
    }

    /**
     * Crée les comptes autorisés « standards » : 34 étudiants et 3 enseignants.
     * Le 35e étudiant, le 4e enseignant et l'administrateur sont les comptes
     * de démonstration créés par creerComptesDemo() (40 comptes au total).
     *
     * @param  array<string, Classe>  $classes
     */
    private function creerComptesAutorises(array $classes): void
    {
        $codesClasses = array_keys($classes);
        $nbClasses = count($codesClasses);

        for ($i = 1; $i <= self::NB_ETUDIANTS - 1; $i++) {
            $matricule = sprintf('ETU2026-%03d', $i);

            CompteAutorise::updateOrCreate(
                ['matricule' => $matricule],
                [
                    'nom' => $this->nom($i),
                    'prenom' => $this->prenom($i),
                    'email' => sprintf('etu2026-%03d@uniconnect.test', $i),
                    'profil' => Profil::ETUDIANT,
                    'classe_id' => $classes[$codesClasses[($i - 1) % $nbClasses]]->id,
                    'etat' => EtatCompte::NON_CREE,
                ],
            );
        }

        for ($i = 1; $i <= self::NB_ENSEIGNANTS - 1; $i++) {
            $matricule = sprintf('ENS2026-%03d', $i);

            CompteAutorise::updateOrCreate(
                ['matricule' => $matricule],
                [
                    'nom' => $this->nom(self::NB_ETUDIANTS + $i),
                    'prenom' => $this->prenom(self::NB_ETUDIANTS + $i),
                    'email' => sprintf('ens2026-%03d@uniconnect.test', $i),
                    'profil' => Profil::ENSEIGNANT,
                    'classe_id' => null,
                    'etat' => EtatCompte::NON_CREE,
                ],
            );
        }
    }

    /**
     * Crée les trois comptes de démonstration déjà activés (et les autorisations
     * correspondantes), puis accorde tous les droits à l'administrateur.
     *
     * @param  array<string, Classe>  $classes
     */
    private function creerComptesDemo(array $classes): void
    {
        $this->creerCompteDemo(
            matricule: 'ETU-DEMO-001',
            email: 'etudiant@demo.uniconnect',
            nom: 'Benali',
            prenom: 'Amine',
            profil: Profil::ETUDIANT,
            classeId: $classes['L2-INFO-A']->id,
        );

        $this->creerCompteDemo(
            matricule: 'ENS-DEMO-001',
            email: 'enseignant@demo.uniconnect',
            nom: 'Martin',
            prenom: 'Sophie',
            profil: Profil::ENSEIGNANT,
        );

        $admin = $this->creerCompteDemo(
            matricule: 'ADM-DEMO-001',
            email: 'admin@demo.uniconnect',
            nom: 'Diallo',
            prenom: 'Fatou',
            profil: Profil::ADMINISTRATION,
        );

        foreach (Droit::cases() as $droit) {
            $admin->accorderDroit($droit);
        }
    }

    private function creerCompteDemo(
        string $matricule,
        string $email,
        string $nom,
        string $prenom,
        Profil $profil,
        ?int $classeId = null,
    ): User {
        $compte = CompteAutorise::updateOrCreate(
            ['matricule' => $matricule],
            [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'profil' => $profil,
                'classe_id' => $classeId,
                'etat' => EtatCompte::ACTIF,
            ],
        );

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'matricule' => $matricule,
                'nom' => $nom,
                'prenom' => $prenom,
                'password' => Hash::make(self::MOT_DE_PASSE),
                'profil' => $profil,
                'classe_id' => $classeId,
                'etat' => EtatCompte::ACTIF,
                'email_verified_at' => now(),
            ],
        );

        $compte->update(['user_id' => $user->id]);

        return $user;
    }

    // 16 prénoms et 13 noms : les combinaisons ne se répètent pas avant 208 comptes.
    private function prenom(int $rang): string
    {
        return self::PRENOMS[($rang - 1) % count(self::PRENOMS)];
    }

    private function nom(int $rang): string
    {
        return self::NOMS[($rang - 1) % count(self::NOMS)];
    }
}
