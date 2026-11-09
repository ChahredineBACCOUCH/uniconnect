<?php

namespace Tests\Feature;

use App\Models\AnneeUniversitaire;
use App\Models\CompteAutorise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SCRUM-62 : vérifier qu'une personne a le droit de s'inscrire (elle doit être
 * dans la liste des comptes autorisés) avant de créer son compte.
 *
 * Base de test : sqlite en mémoire (voir phpunit.xml), jamais la base de données réelle.
 */
class EligibiliteInscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/auth/register';

    private const CORPS = [
        'matricule' => 'LI2-2026-0457',
        'nom' => 'Ben Salah',
        'prenom' => 'Yassine',
        'email' => 'yassine.bensalah@univ.tn',
        'charte' => '1',
    ];

    public function test_sans_annee_active_le_refus_est_plateforme_fermee(): void
    {
        // Aucune année universitaire créée : la plateforme est fermée.
        $response = $this->postJson(self::URL, self::CORPS);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PLATEFORME_FERMEE')
            ->assertJsonPath('message', "La plateforme est fermée : aucune année universitaire n'est active, la création de compte est momentanément indisponible.")
            ->assertJsonStructure(['message', 'code', 'errors']);

        $this->assertSame(0, User::count());
        $this->assertSame(0, CompteAutorise::count());
    }

    public function test_hors_liste_des_comptes_autorises_le_refus_est_non_autorise(): void
    {
        $this->anneeActive();

        // Le matricule existe dans la liste, mais pas avec cet e-mail : le couple ne correspond pas.
        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'autre.adresse@univ.tn',
            'profil' => 'ETUDIANT',
        ]);

        $response = $this->postJson(self::URL, self::CORPS);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'NON_AUTORISE')
            ->assertJsonPath('message', "Ce matricule n'est pas autorisé à créer un compte : le couple matricule et adresse e-mail ne figure pas dans la liste des comptes autorisés.")
            ->assertJsonPath('errors.email.0', "Ce matricule n'est pas autorisé à créer un compte : le couple matricule et adresse e-mail ne figure pas dans la liste des comptes autorisés.");

        $this->assertSame(0, User::count());
    }

    public function test_compte_deja_cree_le_refus_est_deja_inscrit(): void
    {
        $this->anneeActive();

        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.tn',
            'profil' => 'ETUDIANT',
        ]);
        User::factory()->create(['matricule' => 'LI2-2026-0457']);

        $response = $this->postJson(self::URL, self::CORPS);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'DEJA_INSCRIT')
            ->assertJsonPath('message', "Un compte a déjà été créé pour ce matricule : l'inscription a déjà été effectuée.")
            ->assertJsonStructure(['message', 'code', 'errors']);

        $this->assertSame(1, User::count());
    }

    public function test_personne_eligible_rec_une_reponse_positive(): void
    {
        $this->anneeActive();

        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.tn',
            'profil' => 'ETUDIANT',
        ]);

        $response = $this->postJson(self::URL, self::CORPS);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Vous êtes autorisé à créer un compte.');

        $this->assertSame(0, User::count());
    }

    public function test_formulaire_invalide_renvoie_422_avec_messages_francais(): void
    {
        $response = $this->postJson(self::URL, [
            'matricule' => '',
            'email' => 'pas-un-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.matricule.0', 'Le matricule est obligatoire.')
            ->assertJsonPath('errors.email.0', "Le format de l'adresse e-mail est invalide.")
            ->assertJsonPath('errors.charte.0', 'Vous devez accepter la charte pour créer un compte.');
    }

    /** Crée l'année universitaire active (une seule à la fois). */
    private function anneeActive(): void
    {
        AnneeUniversitaire::create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => true,
        ]);
    }
}
