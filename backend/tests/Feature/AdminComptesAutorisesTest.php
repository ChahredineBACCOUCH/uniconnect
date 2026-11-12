<?php

namespace Tests\Feature;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-75 : API d'administration des personnes autorisées à créer un compte.
 */
class AdminComptesAutorisesTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function administrateur(): User
    {
        $user = User::factory()->create([
            'profil' => Profil::ADMINISTRATION,
            'etat' => EtatCompte::ACTIF,
        ]);

        $user->accorderDroit(Droit::GERER_UTILISATEURS);

        return $user;
    }

    private function classe(string $code = 'L2-INFO-A'): Classe
    {
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

        return Classe::create([
            'code' => $code,
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function compte(array $attributs = []): CompteAutorise
    {
        $this->sequence++;

        return CompteAutorise::create(array_merge([
            'matricule' => sprintf('MAT-2026-%03d', $this->sequence),
            'nom' => 'Nom '.$this->sequence,
            'prenom' => 'Prenom '.$this->sequence,
            'email' => sprintf('compte%03d@univ.test', $this->sequence),
            'profil' => Profil::ENSEIGNANT,
            'classe_id' => null,
            'etat' => EtatCompte::NON_CREE,
        ], $attributs));
    }

    public function test_un_invite_ne_peut_pas_acceder_aux_comptes_autorises(): void
    {
        $this->getJson('/api/admin/comptes-autorises')->assertUnauthorized();
    }

    public function test_un_utilisateur_sans_le_droit_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/comptes-autorises')->assertForbidden();
        $this->postJson('/api/admin/comptes-autorises', [
            'matricule' => 'MAT-2026-999',
            'nom' => 'Nom',
            'prenom' => 'Prenom',
            'email' => 'nouveau@univ.test',
            'profil' => Profil::ENSEIGNANT->value,
        ])->assertForbidden();
    }

    public function test_l_administrateur_liste_les_comptes_autorises_20_par_page(): void
    {
        foreach (range(1, 25) as $index) {
            $this->compte(['nom' => sprintf('Nom %02d', $index)]);
        }

        Sanctum::actingAs($this->administrateur());

        $this->getJson('/api/admin/comptes-autorises')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.par_page', 20)
            ->assertJsonPath('meta.total', 25);

        $this->getJson('/api/admin/comptes-autorises?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.page', 2);
    }

    public function test_les_filtres_et_la_recherche_s_appliquent(): void
    {
        $classe = $this->classe();

        $ben = $this->compte([
            'matricule' => 'LI2-2026-001',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'profil' => Profil::ETUDIANT,
            'classe_id' => $classe->id,
        ]);

        $this->compte([
            'matricule' => 'LI2-2026-002',
            'nom' => 'Martin',
            'prenom' => 'Alice',
            'profil' => Profil::ETUDIANT,
            'classe_id' => $classe->id,
            'etat' => EtatCompte::ACTIF,
        ]);

        $enseignant = $this->compte([
            'matricule' => 'ENS-2026-001',
            'nom' => 'Durand',
            'prenom' => 'Paul',
            'profil' => Profil::ENSEIGNANT,
        ]);

        Sanctum::actingAs($this->administrateur());

        $this->getJson('/api/admin/comptes-autorises?profil=ETUDIANT')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/admin/comptes-autorises?classe_id={$classe->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/admin/comptes-autorises?etat=ACTIF')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/admin/comptes-autorises?q=ben')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ben->id);

        $this->getJson('/api/admin/comptes-autorises?q=ENS-2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enseignant->id);

        $this->getJson('/api/admin/comptes-autorises?profil=ETUDIANT&etat=NON_CREE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ben->id);
    }

    public function test_l_administrateur_cree_un_etudiant_avec_sa_classe(): void
    {
        $classe = $this->classe();

        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/comptes-autorises', [
            'matricule' => 'LI2-2026-500',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.test',
            'profil' => Profil::ETUDIANT->value,
            'classe_id' => $classe->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.matricule', 'LI2-2026-500')
            ->assertJsonPath('data.profil', Profil::ETUDIANT->value)
            ->assertJsonPath('data.classe.id', $classe->id)
            ->assertJsonPath('data.etat', EtatCompte::NON_CREE->value);

        $this->assertDatabaseHas('comptes_autorises', [
            'matricule' => 'LI2-2026-500',
            'email' => 'yassine.bensalah@univ.test',
            'classe_id' => $classe->id,
            'etat' => EtatCompte::NON_CREE->value,
        ]);
    }

    public function test_un_enseignant_peut_etre_cree_sans_classe(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/comptes-autorises', [
            'matricule' => 'ENS-2026-500',
            'nom' => 'Durand',
            'prenom' => 'Paul',
            'email' => 'paul.durand@univ.test',
            'profil' => Profil::ENSEIGNANT->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.classe', null);
    }

    public function test_un_etudiant_sans_classe_est_refuse(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/comptes-autorises', [
            'matricule' => 'LI2-2026-501',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.test',
            'profil' => Profil::ETUDIANT->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.classe_id.0', 'La classe est obligatoire pour un étudiant.');
    }

    public function test_le_matricule_et_l_email_doivent_etre_uniques(): void
    {
        $this->compte([
            'matricule' => 'LI2-2026-001',
            'email' => 'existant@univ.test',
        ]);

        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/comptes-autorises', [
            'matricule' => 'LI2-2026-001',
            'nom' => 'Autre',
            'prenom' => 'Personne',
            'email' => 'autre@univ.test',
            'profil' => Profil::ENSEIGNANT->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('matricule');

        $this->postJson('/api/admin/comptes-autorises', [
            'matricule' => 'LI2-2026-002',
            'nom' => 'Autre',
            'prenom' => 'Personne',
            'email' => 'existant@univ.test',
            'profil' => Profil::ENSEIGNANT->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_l_administrateur_modifie_un_compte_autorise(): void
    {
        $classe = $this->classe();
        $compte = $this->compte();

        Sanctum::actingAs($this->administrateur());

        $this->patchJson("/api/admin/comptes-autorises/{$compte->id}", [
            'nom' => 'Nouveau nom',
            'profil' => Profil::ETUDIANT->value,
            'classe_id' => $classe->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.nom', 'Nouveau nom')
            ->assertJsonPath('data.classe.id', $classe->id);

        $this->assertDatabaseHas('comptes_autorises', [
            'id' => $compte->id,
            'nom' => 'Nouveau nom',
            'profil' => Profil::ETUDIANT->value,
            'classe_id' => $classe->id,
        ]);
    }

    public function test_la_mise_a_jour_exige_une_classe_pour_un_etudiant(): void
    {
        $classe = $this->classe();
        $enseignant = $this->compte();

        $etudiant = $this->compte([
            'profil' => Profil::ETUDIANT,
            'classe_id' => $classe->id,
        ]);

        Sanctum::actingAs($this->administrateur());

        // Passage à ETUDIANT sans classe : refusé.
        $this->patchJson("/api/admin/comptes-autorises/{$enseignant->id}", [
            'profil' => Profil::ETUDIANT->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('classe_id');

        // Retrait de la classe d'un étudiant : refusé.
        $this->patchJson("/api/admin/comptes-autorises/{$etudiant->id}", [
            'classe_id' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('classe_id');

        // Un étudiant peut devenir enseignant et perdre sa classe.
        $this->patchJson("/api/admin/comptes-autorises/{$etudiant->id}", [
            'profil' => Profil::ENSEIGNANT->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.profil', Profil::ENSEIGNANT->value)
            ->assertJsonPath('data.classe.id', $classe->id);
    }

    public function test_desactiver_change_l_etat_sans_supprimer(): void
    {
        $compte = $this->compte(['etat' => EtatCompte::NON_CREE]);

        Sanctum::actingAs($this->administrateur());

        $this->postJson("/api/admin/comptes-autorises/{$compte->id}/desactiver")
            ->assertOk()
            ->assertJsonPath('data.etat', EtatCompte::DESACTIVE->value);

        $this->assertDatabaseHas('comptes_autorises', [
            'id' => $compte->id,
            'etat' => EtatCompte::DESACTIVE->value,
        ]);
    }

    public function test_supprimer_une_autorisation_sans_compte(): void
    {
        $compte = $this->compte();

        Sanctum::actingAs($this->administrateur());

        $this->deleteJson("/api/admin/comptes-autorises/{$compte->id}")->assertNoContent();

        $this->assertDatabaseMissing('comptes_autorises', ['id' => $compte->id]);
    }

    public function test_supprimer_une_autorisation_rattachee_a_un_compte_renvoie_409(): void
    {
        $user = User::factory()->create();
        $compte = $this->compte(['user_id' => $user->id]);

        Sanctum::actingAs($this->administrateur());

        $this->deleteJson("/api/admin/comptes-autorises/{$compte->id}")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                "Ce compte a déjà été créé : l'autorisation ne peut pas être supprimée. Désactivez-la à la place.",
            );

        $this->assertDatabaseHas('comptes_autorises', ['id' => $compte->id]);
    }

    public function test_les_filtres_invalides_sont_refuses(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->getJson('/api/admin/comptes-autorises?profil=INCONNU')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profil');

        $this->getJson('/api/admin/comptes-autorises?etat=INCONNU')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etat');

        $this->getJson('/api/admin/comptes-autorises?classe_id=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('classe_id');
    }

    public function test_une_autorisation_inconnue_renvoie_404(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->patchJson('/api/admin/comptes-autorises/999', ['nom' => 'Nom'])
            ->assertNotFound();

        $this->postJson('/api/admin/comptes-autorises/999/desactiver')
            ->assertNotFound();

        $this->deleteJson('/api/admin/comptes-autorises/999')
            ->assertNotFound();
    }
}
