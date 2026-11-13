<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Departement;
use App\Models\Espace;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-90 : membres d'un espace. Un espace a toujours exactement un propriétaire.
 */
class MembreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_membre_liste_les_membres_avec_leur_role(): void
    {
        // Noms fixes : la recherche porte sur un fragment de nom, et faker peut
        // générer un autre membre dont le nom contient « lina » (Célina…).
        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Groupe de révision']);
        $proprietaire = $this->membre($espace, RoleMembre::PROPRIETAIRE, ['nom' => 'Trabelsi', 'prenom' => 'Sami']);
        $co = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE, ['nom' => 'Zitouni', 'prenom' => 'Karim']);
        $membre = $this->membre($espace, RoleMembre::MEMBRE, ['nom' => 'Ayari', 'prenom' => 'Lina']);

        Sanctum::actingAs($membre);

        $this->getJson("/api/espaces/{$espace->id}/membres")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            // Propriétaire, puis co-gestionnaires, puis membres.
            ->assertJsonPath('data.0.user.id', $proprietaire->id)
            ->assertJsonPath('data.0.role', 'PROPRIETAIRE')
            ->assertJsonPath('data.1.user.id', $co->id)
            ->assertJsonPath('data.2.user.id', $membre->id)
            ->assertJsonStructure(['data' => [['user' => ['id', 'nom', 'prenom', 'profil'], 'role', 'depuis']]]);

        $this->getJson("/api/espaces/{$espace->id}/membres?q=lina")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $membre->id);
    }

    public function test_un_non_membre_ne_voit_pas_les_membres(): void
    {
        [$espace] = $this->groupe();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/espaces/{$espace->id}/membres")->assertForbidden();
    }

    public function test_le_proprietaire_nomme_un_co_gestionnaire_mais_un_co_gestionnaire_ne_le_peut_pas(): void
    {
        [$espace, $proprietaire] = $this->groupe();
        $co = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE);
        $membre = $this->membre($espace);

        Sanctum::actingAs($co);
        $this->patchJson("/api/espaces/{$espace->id}/membres/{$membre->id}", ['role' => 'CO_GESTIONNAIRE'])
            ->assertForbidden();

        Sanctum::actingAs($proprietaire);
        $this->patchJson("/api/espaces/{$espace->id}/membres/{$membre->id}", ['role' => 'CO_GESTIONNAIRE'])
            ->assertOk()
            ->assertJsonPath('data.role', 'CO_GESTIONNAIRE');

        // Le rôle PROPRIETAIRE ne se donne pas par ce chemin.
        $this->patchJson("/api/espaces/{$espace->id}/membres/{$membre->id}", ['role' => 'PROPRIETAIRE'])
            ->assertUnprocessable();
        $this->assertSame(1, $this->nombreDeProprietaires($espace));
    }

    public function test_retirer_un_membre_coupe_son_acces_immediatement(): void
    {
        [$espace, $proprietaire] = $this->groupe();
        $membre = $this->membre($espace);

        Sanctum::actingAs($proprietaire);
        $this->deleteJson("/api/espaces/{$espace->id}/membres/{$membre->id}")->assertNoContent();

        Sanctum::actingAs($membre);
        $this->getJson("/api/espaces/{$espace->id}/messages")->assertForbidden();
        $this->getJson("/api/espaces/{$espace->id}")->assertForbidden();
    }

    public function test_on_ne_peut_pas_retirer_le_proprietaire(): void
    {
        [$espace, $proprietaire] = $this->groupe();
        $co = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE);

        Sanctum::actingAs($co);

        $this->deleteJson("/api/espaces/{$espace->id}/membres/{$proprietaire->id}")
            ->assertUnprocessable();
        $this->assertSame(1, $this->nombreDeProprietaires($espace));
    }

    public function test_un_co_gestionnaire_retire_un_membre_mais_pas_un_autre_co_gestionnaire(): void
    {
        [$espace] = $this->groupe();
        $co = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE);
        $autreCo = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE);
        $membre = $this->membre($espace);

        Sanctum::actingAs($co);

        $this->deleteJson("/api/espaces/{$espace->id}/membres/{$autreCo->id}")->assertForbidden();
        $this->deleteJson("/api/espaces/{$espace->id}/membres/{$membre->id}")->assertNoContent();
    }

    public function test_un_simple_membre_ne_peut_retirer_personne(): void
    {
        [$espace] = $this->groupe();
        $membre = $this->membre($espace);
        $autre = $this->membre($espace);

        Sanctum::actingAs($membre);

        $this->deleteJson("/api/espaces/{$espace->id}/membres/{$autre->id}")->assertForbidden();
    }

    public function test_transferer_la_propriete_laisse_un_seul_proprietaire(): void
    {
        [$espace, $proprietaire] = $this->groupe();
        $membre = $this->membre($espace);

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/espaces/{$espace->id}/transferer", ['user_id' => $membre->id])
            ->assertOk()
            ->assertJsonPath('data.role', 'PROPRIETAIRE');

        $this->assertSame(RoleMembre::PROPRIETAIRE, $this->role($espace, $membre));
        $this->assertSame(RoleMembre::CO_GESTIONNAIRE, $this->role($espace, $proprietaire));
        $this->assertSame(1, $this->nombreDeProprietaires($espace));
    }

    public function test_on_ne_transfere_pas_a_un_non_membre_ni_sans_etre_proprietaire(): void
    {
        [$espace, $proprietaire] = $this->groupe();
        $co = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE);

        Sanctum::actingAs($co);
        $this->postJson("/api/espaces/{$espace->id}/transferer", ['user_id' => $co->id])->assertForbidden();

        Sanctum::actingAs($proprietaire);
        $this->postJson("/api/espaces/{$espace->id}/transferer", ['user_id' => User::factory()->create()->id])
            ->assertUnprocessable();
        $this->assertSame(RoleMembre::PROPRIETAIRE, $this->role($espace, $proprietaire));
    }

    public function test_un_membre_quitte_l_espace(): void
    {
        [$espace] = $this->groupe();
        $membre = $this->membre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/quitter")->assertNoContent();
        $this->assertNull($this->role($espace, $membre));
    }

    public function test_le_proprietaire_qui_part_transmet_au_plus_ancien_co_gestionnaire(): void
    {
        [$espace, $proprietaire] = $this->groupe();
        $ancienCo = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE);
        $this->travel(1)->minutes();
        $recentCo = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE);

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/espaces/{$espace->id}/quitter")->assertNoContent();

        $this->assertNull($this->role($espace, $proprietaire));
        $this->assertSame(RoleMembre::PROPRIETAIRE, $this->role($espace, $ancienCo));
        $this->assertSame(RoleMembre::CO_GESTIONNAIRE, $this->role($espace, $recentCo));
        $this->assertSame(1, $this->nombreDeProprietaires($espace));
    }

    public function test_sans_co_gestionnaire_l_administration_reprend_l_espace(): void
    {
        [$espace, $proprietaire] = $this->groupe();
        $this->membre($espace); // un simple membre ne devient pas propriétaire
        $admin = User::factory()->create(['profil' => 'ADMINISTRATION', 'etat' => 'ACTIF']);

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/espaces/{$espace->id}/quitter")->assertNoContent();

        $this->assertSame(RoleMembre::PROPRIETAIRE, $this->role($espace, $admin));
        // Critère du ticket : l'espace a toujours exactement un propriétaire.
        $this->assertSame(1, $this->nombreDeProprietaires($espace));
    }

    public function test_sans_successeur_possible_le_proprietaire_ne_peut_pas_partir(): void
    {
        [$espace, $proprietaire] = $this->groupe();

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/espaces/{$espace->id}/quitter")->assertStatus(409);
        $this->assertSame(RoleMembre::PROPRIETAIRE, $this->role($espace, $proprietaire));
    }

    public function test_on_ne_quitte_pas_le_groupe_organisationnel_de_sa_classe(): void
    {
        $annee = AnneeUniversitaire::create(['libelle' => '2026/2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'active' => true]);
        $departement = Departement::create(['code' => 'INFO', 'libelle' => 'Informatique']);
        $filiere = Filiere::create(['code' => 'GL', 'libelle' => 'Génie Logiciel', 'departement_id' => $departement->id]);
        $niveau = Niveau::create(['code' => 'L2', 'libelle' => 'Licence 2']);
        $classe = Classe::create(['code' => 'L2-A', 'libelle' => 'L2 A', 'annee_id' => $annee->id, 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id]);
        $espace = Espace::create(['type' => 'ORGANISATIONNEL', 'nom' => 'L2 A', 'annee_id' => $annee->id, 'classe_id' => $classe->id]);
        $etudiant = $this->membre($espace);

        Sanctum::actingAs($etudiant);

        $this->postJson("/api/espaces/{$espace->id}/quitter")->assertForbidden();
        $this->assertSame(RoleMembre::MEMBRE, $this->role($espace, $etudiant));
    }

    /** @return array{0: Espace, 1: User} */
    private function groupe(): array
    {
        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Groupe de révision']);

        return [$espace, $this->membre($espace, RoleMembre::PROPRIETAIRE)];
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function membre(Espace $espace, RoleMembre $role = RoleMembre::MEMBRE, array $attributs = []): User
    {
        $user = User::factory()->create($attributs);
        Adhesion::create(['espace_id' => $espace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function role(Espace $espace, User $user): ?RoleMembre
    {
        return Adhesion::where('espace_id', $espace->id)->where('user_id', $user->id)->first()?->role;
    }

    private function nombreDeProprietaires(Espace $espace): int
    {
        return Adhesion::where('espace_id', $espace->id)->where('role', RoleMembre::PROPRIETAIRE)->count();
    }
}
