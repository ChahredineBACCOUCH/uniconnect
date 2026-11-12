<?php

namespace Tests\Feature\Admin;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\CompteAutorise;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-78 : tests de l'import à partir des fichiers CSV de tests/fixtures.
 *
 * Numéros de ligne : la ligne 1 est l'en-tête, la première personne est en ligne 2.
 */
class ImportComptesTest extends TestCase
{
    use RefreshDatabase;

    private const APERCU = '/api/admin/comptes-autorises/import/apercu';

    private const VALIDER = '/api/admin/comptes-autorises/import/valider';

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        $annee = AnneeUniversitaire::create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => true,
        ]);
        $departement = Departement::create(['code' => 'INFO', 'libelle' => 'Informatique']);
        $filiere = Filiere::create([
            'code' => 'GL',
            'libelle' => 'Génie Logiciel',
            'departement_id' => $departement->id,
        ]);
        $niveau = Niveau::create(['code' => 'L2', 'libelle' => 'Licence 2']);

        $this->classe = Classe::create([
            'code' => 'L2-INFO-A',
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ]);

        $admin = User::factory()->create([
            'profil' => Profil::ADMINISTRATION,
            'etat' => EtatCompte::ACTIF,
        ]);
        $admin->accorderDroit(Droit::GERER_UTILISATEURS);
        Sanctum::actingAs($admin);
    }

    private function apercu(string $fixture): TestResponse
    {
        $fichier = UploadedFile::fake()->createWithContent(
            $fixture,
            file_get_contents(base_path('tests/fixtures/'.$fixture)),
        );

        return $this->post(self::APERCU, ['fichier' => $fichier], ['Accept' => 'application/json'])
            ->assertOk();
    }

    /**
     * @return array<int, string> motif par numéro de ligne
     */
    private function erreursParLigne(TestResponse $reponse): array
    {
        return collect($reponse->json('erreurs'))->pluck('motif', 'ligne')->all();
    }

    public function test_les_doublons_sont_detectes(): void
    {
        CompteAutorise::create([
            'matricule' => 'MAT-EXISTANT',
            'nom' => 'Ancien',
            'prenom' => 'Compte',
            'email' => 'ancien@univ.test',
            'profil' => Profil::ENSEIGNANT,
        ]);

        $reponse = $this->apercu('import_doublon.csv')
            ->assertJsonPath('total', 4)
            ->assertJsonPath('valides', 1);

        $erreurs = $this->erreursParLigne($reponse);

        $this->assertSame([3, 4, 5], array_keys($erreurs));
        $this->assertStringContainsString('matricule figure plusieurs fois', $erreurs[3]);
        $this->assertStringContainsString('e-mail figure plusieurs fois', $erreurs[4]);
        $this->assertStringContainsString('matricule figure déjà', $erreurs[5]);
    }

    public function test_un_email_invalide_est_signale_avec_son_numero_de_ligne(): void
    {
        $reponse = $this->apercu('import_email_invalide.csv')
            ->assertJsonPath('total', 4)
            ->assertJsonPath('valides', 2);

        $this->assertSame(
            [3 => "L'adresse e-mail est invalide.", 5 => "L'adresse e-mail est invalide."],
            $this->erreursParLigne($reponse),
        );
    }

    public function test_une_classe_inconnue_est_signalee(): void
    {
        $reponse = $this->apercu('import_classe_inconnue.csv')
            ->assertJsonPath('valides', 1)
            ->assertJsonCount(1, 'erreurs')
            ->assertJsonPath('erreurs.0.ligne', 3);

        $this->assertSame(
            "La classe « M1-INEXISTANTE » n'existe pas pour l'année universitaire active.",
            $reponse->json('erreurs.0.motif'),
        );
    }

    public function test_l_apercu_n_enregistre_rien(): void
    {
        CompteAutorise::create([
            'matricule' => 'MAT-EXISTANT',
            'nom' => 'Ancien',
            'prenom' => 'Compte',
            'email' => 'ancien@univ.test',
            'profil' => Profil::ENSEIGNANT,
        ]);
        $avant = CompteAutorise::count();

        foreach (['import_ok.csv', 'import_doublon.csv', 'import_email_invalide.csv', 'import_classe_inconnue.csv'] as $fixture) {
            $this->apercu($fixture);
        }

        $this->assertSame($avant, CompteAutorise::count());
        $this->assertDatabaseCount('imports_comptes', 0);
    }

    public function test_apercu_puis_validation_enregistre_les_lignes(): void
    {
        $apercu = $this->apercu('import_ok.csv')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('valides', 3)
            ->assertJsonCount(0, 'erreurs');

        $this->postJson(self::VALIDER, [
            'lignes' => $apercu->json('lignes'),
            'fichier_nom' => 'import_ok.csv',
        ])
            ->assertCreated()
            ->assertJsonPath('importes', 3)
            ->assertJsonPath('ignores', 0);

        $this->assertSame(3, CompteAutorise::count());
        $this->assertDatabaseHas('comptes_autorises', [
            'matricule' => 'MAT-OK-001',
            'email' => 'sami.benali@univ.test',
            'profil' => Profil::ETUDIANT->value,
            'classe_id' => $this->classe->id,
            'etat' => EtatCompte::NON_CREE->value,
        ]);
        // Les accents du CSV (UTF-8) sont conservés.
        $this->assertDatabaseHas('comptes_autorises', ['matricule' => 'MAT-OK-002', 'prenom' => 'Leïla', 'classe_id' => null]);
        $this->assertDatabaseHas('comptes_autorises', ['matricule' => 'MAT-OK-003', 'profil' => Profil::ADMINISTRATION->value]);
        $this->assertDatabaseHas('imports_comptes', ['fichier' => 'import_ok.csv', 'importes' => 3, 'ignores' => 0]);
    }

    public function test_valider_deux_fois_le_meme_fichier_ne_cree_pas_de_doublon(): void
    {
        $lignes = $this->apercu('import_ok.csv')->json('lignes');

        $this->postJson(self::VALIDER, ['lignes' => $lignes])->assertCreated();
        $this->postJson(self::VALIDER, ['lignes' => $lignes])
            ->assertCreated()
            ->assertJsonPath('importes', 0)
            ->assertJsonPath('ignores', 3);

        $this->assertSame(3, CompteAutorise::count());
    }
}
