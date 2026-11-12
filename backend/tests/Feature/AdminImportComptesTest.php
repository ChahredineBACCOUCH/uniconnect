<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Exports\ModeleComptesExport;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\CompteAutorise;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * SCRUM-76 : import Excel des comptes autorisés (aperçu puis validation).
 */
class AdminImportComptesTest extends TestCase
{
    use RefreshDatabase;

    private function administrateur(): User
    {
        $user = User::factory()->create([
            'profil' => Profil::ADMINISTRATION,
            'etat' => EtatCompte::ACTIF,
        ]);

        $user->accorderDroit(Droit::GERER_UTILISATEURS);

        return $user;
    }

    private function annee(bool $active = true, string $libelle = '2026/2027'): AnneeUniversitaire
    {
        return AnneeUniversitaire::create([
            'libelle' => $libelle,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => $active,
        ]);
    }

    private function classe(string $code = 'L2-INFO-A', ?AnneeUniversitaire $annee = null): Classe
    {
        $annee ??= $this->annee();

        $departement = Departement::create(['code' => 'INFO', 'libelle' => 'Informatique']);

        $filiere = Filiere::create([
            'code' => 'GL',
            'libelle' => 'Génie Logiciel',
            'departement_id' => $departement->id,
        ]);

        $niveau = Niveau::create(['code' => 'L2', 'libelle' => 'Licence 2']);

        return Classe::create([
            'code' => $code,
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ]);
    }

    /**
     * Construit un classeur Excel à partir de lignes brutes.
     *
     * @param  array<int, array<int, mixed>>  $lignes
     */
    private function fichier(array $lignes): File
    {
        $contenu = Excel::raw(new ModeleComptesExport($lignes), ExcelWriter::XLSX);

        return File::createWithContent('comptes.xlsx', $contenu);
    }

    /**
     * 40 lignes dont 2 erreurs : ligne 15 (e-mail invalide) et ligne 28
     * (classe inconnue).
     *
     * @return array<int, array<int, mixed>>
     */
    private function lignes(int $total = 40): array
    {
        $lignes = [];

        for ($index = 0; $index < $total; $index++) {
            $numero = $index + 1;
            $etudiant = $numero % 2 === 1;

            $lignes[] = [
                sprintf('MAT-2026-%03d', $numero),
                'Nom '.$numero,
                'Prenom '.$numero,
                sprintf('compte%03d@univ.test', $numero),
                $etudiant ? 'ETUDIANT' : 'ENSEIGNANT',
                $etudiant ? 'L2-INFO-A' : '',
            ];
        }

        $lignes[13][3] = 'pas-un-email';
        $lignes[26][5] = 'CLASSE-INCONNUE';

        return $lignes;
    }

    public function test_un_invite_ne_peut_pas_importer_des_comptes(): void
    {
        $this->postJson('/api/admin/comptes-autorises/import/apercu')->assertUnauthorized();
        $this->postJson('/api/admin/comptes-autorises/import/valider')->assertUnauthorized();
        $this->getJson('/api/admin/comptes-autorises/import/modele')->assertUnauthorized();
    }

    public function test_un_utilisateur_sans_le_droit_est_refuse(): void
    {
        $this->classe();

        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/admin/comptes-autorises/import/apercu', [
            'fichier' => $this->fichier($this->lignes(1)),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->postJson('/api/admin/comptes-autorises/import/valider', [
            'lignes' => [['ligne' => 2, 'matricule' => 'MAT-1', 'nom' => 'Nom', 'prenom' => 'Prenom', 'email' => 'a@b.test', 'profil' => 'ENSEIGNANT']],
        ])->assertForbidden();

        $this->getJson('/api/admin/comptes-autorises/import/modele')->assertForbidden();
    }

    public function test_l_apercu_de_40_lignes_dont_2_erreurs_n_ecrit_rien(): void
    {
        $this->classe();

        Sanctum::actingAs($this->administrateur());

        $reponse = $this->post('/api/admin/comptes-autorises/import/apercu', [
            'fichier' => $this->fichier($this->lignes()),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('total', 40)
            ->assertJsonPath('valides', 38)
            ->assertJsonCount(2, 'erreurs')
            ->assertJsonPath('erreurs.0.ligne', 15)
            ->assertJsonPath('erreurs.1.ligne', 28);

        $this->assertStringContainsString('e-mail', $reponse->json('erreurs.0.motif'));
        $this->assertStringContainsString('CLASSE-INCONNUE', $reponse->json('erreurs.1.motif'));
        $this->assertCount(38, $reponse->json('lignes'));

        // L'aperçu ne doit rien enregistrer.
        $this->assertDatabaseCount('comptes_autorises', 0);
        $this->assertDatabaseCount('imports_comptes', 0);
    }

    public function test_la_validation_importe_les_lignes_valides_et_historise(): void
    {
        $classe = $this->classe();
        $administrateur = $this->administrateur();

        Sanctum::actingAs($administrateur);

        $apercu = $this->post('/api/admin/comptes-autorises/import/apercu', [
            'fichier' => $this->fichier($this->lignes()),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->postJson('/api/admin/comptes-autorises/import/valider', [
            'lignes' => $apercu->json('lignes'),
            'fichier_nom' => 'comptes.xlsx',
        ])
            ->assertCreated()
            ->assertJsonPath('importes', 38)
            ->assertJsonPath('ignores', 0)
            ->assertJsonCount(0, 'erreurs');

        $this->assertDatabaseCount('comptes_autorises', 38);

        $this->assertDatabaseHas('comptes_autorises', [
            'matricule' => 'MAT-2026-001',
            'classe_id' => $classe->id,
            'etat' => EtatCompte::NON_CREE->value,
        ]);

        $this->assertDatabaseHas('comptes_autorises', [
            'matricule' => 'MAT-2026-002',
            'classe_id' => null,
        ]);

        $this->assertDatabaseHas('imports_comptes', [
            'user_id' => $administrateur->id,
            'fichier' => 'comptes.xlsx',
            'total_lignes' => 38,
            'importes' => 38,
            'ignores' => 0,
        ]);
    }

    public function test_le_modele_excel_se_telecharge(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->get('/api/admin/comptes-autorises/import/modele')
            ->assertOk()
            ->assertDownload('modele-import-comptes.xlsx');
    }

    public function test_un_fichier_illisible_est_refuse(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->post('/api/admin/comptes-autorises/import/apercu', [
            'fichier' => File::createWithContent('comptes.xlsx', 'ceci n\'est pas un classeur'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fichier');
    }

    public function test_les_colonnes_attendues_sont_verifiees(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->post('/api/admin/comptes-autorises/import/apercu', [
            'fichier' => File::createWithContent('comptes.csv', "matricule;nom\nMAT-1;Nom"),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fichier');
    }

    public function test_les_doublons_et_les_classes_invalides_sont_signales(): void
    {
        $this->classe();

        CompteAutorise::create([
            'matricule' => 'MAT-EXISTANT',
            'nom' => 'Ancien',
            'prenom' => 'Compte',
            'email' => 'ancien@univ.test',
            'profil' => Profil::ENSEIGNANT,
        ]);

        Sanctum::actingAs($this->administrateur());

        $reponse = $this->post('/api/admin/comptes-autorises/import/apercu', [
            'fichier' => $this->fichier([
                ['MAT-EXISTANT', 'Nom', 'Prenom', 'nouveau@univ.test', 'ENSEIGNANT', ''],
                ['MAT-NOUVEAU', 'Nom', 'Prenom', 'ancien@univ.test', 'ENSEIGNANT', ''],
                ['MAT-ETU-1', 'Nom', 'Prenom', 'etu1@univ.test', 'ETUDIANT', ''],
                ['MAT-ETU-2', 'Nom', 'Prenom', 'etu2@univ.test', 'ETUDIANT', 'INCONNUE'],
                ['MAT-ENS-1', 'Nom', 'Prenom', 'ens1@univ.test', 'ENSEIGNANT', 'L2-INFO-A'],
                ['MAT-DOUBLE', 'Nom', 'Prenom', 'double1@univ.test', 'ENSEIGNANT', ''],
                ['MAT-DOUBLE', 'Nom', 'Prenom', 'double2@univ.test', 'ENSEIGNANT', ''],
                ['MAT-PROFIL', 'Nom', 'Prenom', 'profil@univ.test', 'INCONNU', ''],
            ]),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('total', 8)
            ->assertJsonPath('valides', 1)
            ->assertJsonCount(7, 'erreurs');

        $motifs = collect($reponse->json('erreurs'))->pluck('motif')->implode(' ');

        $this->assertStringContainsString('matricule figure déjà', $motifs);
        $this->assertStringContainsString('e-mail figure déjà', $motifs);
        $this->assertStringContainsString('classe est obligatoire pour un étudiant', $motifs);
        $this->assertStringContainsString('INCONNUE', $motifs);
        $this->assertStringContainsString('ne doit être renseignée que pour un étudiant', $motifs);
        $this->assertStringContainsString('plusieurs fois dans le fichier', $motifs);
        $this->assertStringContainsString('ETUDIANT, ENSEIGNANT ou ADMINISTRATION', $motifs);
    }

    public function test_une_classe_hors_de_l_annee_active_est_refusee(): void
    {
        $ancienne = $this->annee(false, '2025/2026');
        $this->classe('L2-ANCIENNE', $ancienne);
        $this->annee(true);

        Sanctum::actingAs($this->administrateur());

        $this->post('/api/admin/comptes-autorises/import/apercu', [
            'fichier' => $this->fichier([
                ['MAT-1', 'Nom', 'Prenom', 'etu@univ.test', 'ETUDIANT', 'L2-ANCIENNE'],
            ]),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('valides', 0)
            ->assertJsonPath('erreurs.0.ligne', 2)
            ->assertJsonPath(
                'erreurs.0.motif',
                "La classe « L2-ANCIENNE » n'existe pas pour l'année universitaire active.",
            );
    }

    public function test_la_validation_ignore_une_ligne_devenue_invalide(): void
    {
        $classe = $this->classe();

        Sanctum::actingAs($this->administrateur());

        $apercu = $this->post('/api/admin/comptes-autorises/import/apercu', [
            'fichier' => $this->fichier([
                ['MAT-1', 'Nom', 'Prenom', 'etu@univ.test', 'ETUDIANT', 'L2-INFO-A'],
            ]),
        ], ['Accept' => 'application/json'])->assertOk();

        $lignes = $apercu->json('lignes');
        $lignes[0]['email'] = 'pas-un-email';

        $this->postJson('/api/admin/comptes-autorises/import/valider', ['lignes' => $lignes])
            ->assertCreated()
            ->assertJsonPath('importes', 0)
            ->assertJsonPath('ignores', 1);

        $this->assertDatabaseCount('comptes_autorises', 0);
        $this->assertDatabaseHas('imports_comptes', ['importes' => 0, 'ignores' => 1]);

        $this->assertDatabaseMissing('comptes_autorises', ['matricule' => 'MAT-1']);
        $this->assertNotNull($classe);
    }

    public function test_valider_exige_au_moins_une_ligne(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/comptes-autorises/import/valider', ['lignes' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.lignes.0', 'Aucune ligne à importer.');
    }
}
