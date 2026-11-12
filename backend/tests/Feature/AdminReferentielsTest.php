<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-79 : API CRUD des cinq référentiels pédagogiques.
 */
class AdminReferentielsTest extends TestCase
{
    use RefreshDatabase;

    /** Message attendu quand une suppression est refusée. */
    private const MESSAGE_UTILISE = 'Impossible de supprimer : élément utilisé. Vous pouvez le désactiver.';

    /** Chemins des collections, pour les tests transverses. */
    private const RESSOURCES = ['departements', 'filieres', 'niveaux', 'classes', 'matieres'];

    private function administrateur(): User
    {
        $user = User::factory()->create([
            'profil' => Profil::ADMINISTRATION,
            'etat' => EtatCompte::ACTIF,
        ]);

        $user->accorderDroit(Droit::GERER_PARAMETRAGE);

        return $user;
    }

    private function annee(string $libelle = '2026/2027'): AnneeUniversitaire
    {
        return AnneeUniversitaire::create([
            'libelle' => $libelle,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => false,
        ]);
    }

    private function departement(string $code = 'INFO'): Departement
    {
        return Departement::create(['code' => $code, 'libelle' => 'Informatique']);
    }

    private function filiere(Departement $departement, string $code = 'GL'): Filiere
    {
        return Filiere::create([
            'code' => $code,
            'libelle' => 'Génie Logiciel',
            'departement_id' => $departement->id,
        ]);
    }

    private function niveau(string $code = 'L2'): Niveau
    {
        return Niveau::create(['code' => $code, 'libelle' => 'Licence 2']);
    }

    private function classe(
        Filiere $filiere,
        Niveau $niveau,
        AnneeUniversitaire $annee,
        string $code = 'L2-INFO-A',
    ): Classe {
        return Classe::create([
            'code' => $code,
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ]);
    }

    public function test_un_invite_ne_peut_pas_acceder_aux_referentiels(): void
    {
        foreach (self::RESSOURCES as $ressource) {
            $this->getJson("/api/admin/{$ressource}")->assertUnauthorized();
        }
    }

    public function test_un_utilisateur_sans_le_droit_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (self::RESSOURCES as $ressource) {
            $this->getJson("/api/admin/{$ressource}")->assertForbidden();
        }
    }

    public function test_l_administrateur_gere_les_departements(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->getJson('/api/admin/departements')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.par_page', 20)
            ->assertJsonPath('meta.total', 0);

        $id = $this->postJson('/api/admin/departements', [
            'code' => 'INFO',
            'libelle' => 'Informatique',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'INFO')
            ->assertJsonPath('data.actif', true)
            ->json('data.id');

        $this->getJson("/api/admin/departements/{$id}")
            ->assertOk()
            ->assertJsonPath('data.libelle', 'Informatique');

        $this->patchJson("/api/admin/departements/{$id}", ['libelle' => 'Informatique et réseaux'])
            ->assertOk()
            ->assertJsonPath('data.libelle', 'Informatique et réseaux');

        $this->deleteJson("/api/admin/departements/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('departements', ['id' => $id]);
    }

    public function test_les_filtres_et_la_recherche_s_appliquent(): void
    {
        $this->departement('INFO');
        Departement::create(['code' => 'GESTION', 'libelle' => 'Gestion', 'actif' => false]);

        Sanctum::actingAs($this->administrateur());

        $this->getJson('/api/admin/departements?actif=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'GESTION');

        $this->getJson('/api/admin/departements?q=info')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'INFO');

        $this->getJson('/api/admin/departements?par_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.par_page', 1)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_un_departement_utilise_ne_peut_pas_etre_supprime(): void
    {
        $departement = $this->departement();
        $this->filiere($departement);

        Sanctum::actingAs($this->administrateur());

        $this->deleteJson("/api/admin/departements/{$departement->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', self::MESSAGE_UTILISE);

        $this->assertDatabaseHas('departements', ['id' => $departement->id]);
    }

    public function test_une_filiere_exige_un_departement_existant(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/filieres', [
            'code' => 'GL',
            'libelle' => 'Génie Logiciel',
            'departement_id' => 999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('departement_id');

        $this->postJson('/api/admin/filieres', ['code' => 'GL', 'libelle' => 'Génie Logiciel'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.departement_id.0', 'Le département est obligatoire.');
    }

    public function test_le_departement_est_rendu_dans_la_filiere(): void
    {
        $departement = $this->departement();

        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/filieres', [
            'code' => 'GL',
            'libelle' => 'Génie Logiciel',
            'departement_id' => $departement->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.departement.id', $departement->id)
            ->assertJsonPath('data.departement.code', 'INFO');
    }

    public function test_la_classe_est_unique_par_code_et_par_annee(): void
    {
        $departement = $this->departement();
        $filiere = $this->filiere($departement);
        $niveau = $this->niveau();
        $annee = $this->annee();

        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/classes', [
            'code' => 'L2-INFO-A',
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.filiere.code', 'GL')
            ->assertJsonPath('data.niveau.code', 'L2')
            ->assertJsonPath('data.annee.id', $annee->id);

        $this->postJson('/api/admin/classes', [
            'code' => 'L2-INFO-A',
            'libelle' => 'Doublon',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.code.0',
                'Cette classe existe déjà pour cette année universitaire.',
            );

        // Le même code reste disponible pour l'année suivante.
        $this->postJson('/api/admin/classes', [
            'code' => 'L2-INFO-A',
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $this->annee('2027/2028')->id,
        ])->assertCreated();
    }

    public function test_supprimer_une_classe_avec_des_etudiants_est_refuse(): void
    {
        $classe = $this->classe(
            $this->filiere($this->departement()),
            $this->niveau(),
            $this->annee(),
        );

        User::factory()->create([
            'profil' => Profil::ETUDIANT,
            'etat' => EtatCompte::NON_CREE,
            'classe_id' => $classe->id,
        ]);

        Sanctum::actingAs($this->administrateur());

        $this->deleteJson("/api/admin/classes/{$classe->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', self::MESSAGE_UTILISE);

        $this->assertDatabaseHas('classes', ['id' => $classe->id]);

        // La désactivation reste possible.
        $this->patchJson("/api/admin/classes/{$classe->id}/desactiver")
            ->assertOk()
            ->assertJsonPath('data.actif', false);
    }

    public function test_la_matiere_synchronise_les_classes_et_les_enseignants(): void
    {
        $classe = $this->classe(
            $this->filiere($this->departement()),
            $this->niveau(),
            $this->annee(),
        );

        $enseignant = User::factory()->create([
            'profil' => Profil::ENSEIGNANT,
            'etat' => EtatCompte::ACTIF,
        ]);

        Sanctum::actingAs($this->administrateur());

        $id = $this->postJson('/api/admin/matieres', [
            'code' => 'PROG',
            'libelle' => 'Programmation',
            'semestre' => 1,
            'classe_ids' => [$classe->id],
            'enseignant_ids' => [$enseignant->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.classes.0.id', $classe->id)
            ->assertJsonPath('data.enseignants.0.id', $enseignant->id)
            ->json('data.id');

        $this->assertDatabaseHas('classe_matiere', [
            'classe_id' => $classe->id,
            'matiere_id' => $id,
        ]);

        $this->assertDatabaseHas('matiere_enseignant', [
            'matiere_id' => $id,
            'user_id' => $enseignant->id,
        ]);

        // `sync` remplace les liens existants.
        $this->patchJson("/api/admin/matieres/{$id}", [
            'classe_ids' => [],
            'enseignant_ids' => [],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.classes')
            ->assertJsonCount(0, 'data.enseignants');

        $this->assertDatabaseMissing('classe_matiere', ['matiere_id' => $id]);
        $this->assertDatabaseMissing('matiere_enseignant', ['matiere_id' => $id]);
    }

    public function test_une_matiere_utilisee_par_une_classe_ne_peut_pas_etre_supprimee(): void
    {
        $classe = $this->classe(
            $this->filiere($this->departement()),
            $this->niveau(),
            $this->annee(),
        );

        $matiere = Matiere::create([
            'code' => 'PROG',
            'libelle' => 'Programmation',
            'semestre' => 1,
        ]);

        $matiere->classes()->sync([$classe->id]);

        Sanctum::actingAs($this->administrateur());

        $this->deleteJson("/api/admin/matieres/{$matiere->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', self::MESSAGE_UTILISE);
    }

    public function test_le_semestre_doit_valoir_un_ou_deux(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/matieres', [
            'code' => 'PROG',
            'libelle' => 'Programmation',
            'semestre' => 3,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.semestre.0', 'Le semestre doit être 1 ou 2.');
    }

    public function test_les_cinq_referentiels_se_desactivent(): void
    {
        $departement = $this->departement();
        $filiere = $this->filiere($departement);
        $niveau = $this->niveau();
        $annee = $this->annee();
        $classe = $this->classe($filiere, $niveau, $annee);
        $matiere = Matiere::create([
            'code' => 'PROG',
            'libelle' => 'Programmation',
            'semestre' => 1,
        ]);

        Sanctum::actingAs($this->administrateur());

        $cas = [
            'departements' => $departement->id,
            'filieres' => $filiere->id,
            'niveaux' => $niveau->id,
            'classes' => $classe->id,
            'matieres' => $matiere->id,
        ];

        foreach ($cas as $ressource => $id) {
            $this->patchJson("/api/admin/{$ressource}/{$id}/desactiver")
                ->assertOk()
                ->assertJsonPath('data.actif', false);
        }

        $this->assertDatabaseHas('niveaux', ['id' => $niveau->id, 'actif' => false]);
    }

    public function test_un_referentiel_inconnu_renvoie_404(): void
    {
        Sanctum::actingAs($this->administrateur());

        foreach (self::RESSOURCES as $ressource) {
            $this->getJson("/api/admin/{$ressource}/999")->assertNotFound();
            $this->patchJson("/api/admin/{$ressource}/999", ['libelle' => 'Inconnu'])->assertNotFound();
            $this->deleteJson("/api/admin/{$ressource}/999")->assertNotFound();
        }
    }

    public function test_le_code_est_obligatoire_avec_un_message_francais(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/departements', ['libelle' => 'Informatique'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Le code est obligatoire.');

        $this->postJson('/api/admin/matieres', [
            'code' => 'PROG',
            'libelle' => 'Programmation',
            'semestre' => 1,
            'classe_ids' => [999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('classe_ids.0');
    }

    public function test_les_enseignants_actifs_sont_proposes_pour_les_matieres(): void
    {
        $enseignant = User::factory()->create([
            'nom' => 'Ben Ali',
            'prenom' => 'Sana',
            'profil' => Profil::ENSEIGNANT,
            'etat' => EtatCompte::ACTIF,
        ]);

        User::factory()->create([
            'profil' => Profil::ENSEIGNANT,
            'etat' => EtatCompte::DESACTIVE,
        ]);
        User::factory()->create([
            'profil' => Profil::ADMINISTRATION,
            'etat' => EtatCompte::ACTIF,
        ]);

        Sanctum::actingAs($this->administrateur());

        $this->getJson('/api/admin/matieres/enseignants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enseignant->id)
            ->assertJsonPath('data.0.nom', 'Ben Ali')
            ->assertJsonPath('data.0.prenom', 'Sana')
            ->assertJsonPath('data.0.email', $enseignant->email);
    }

    public function test_la_liste_des_enseignants_est_protegee(): void
    {
        $this->getJson('/api/admin/matieres/enseignants')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/matieres/enseignants')->assertForbidden();
    }
}
