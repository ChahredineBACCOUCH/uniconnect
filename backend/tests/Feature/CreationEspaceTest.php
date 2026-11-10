<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-87 : création d'un groupe privé et EspacePolicy.
 */
class CreationEspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_etudiant_sans_le_droit_recoit_403(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/espaces', ['nom' => 'Révisions BD'])
            ->assertForbidden()
            ->assertJsonPath('message', "Vous n'avez pas le droit de créer un groupe privé.");

        $this->assertSame(0, Espace::count());
    }

    public function test_avec_le_droit_le_createur_devient_proprietaire(): void
    {
        $createur = $this->avecDroit();
        Sanctum::actingAs($createur);

        $reponse = $this->postJson('/api/espaces', [
            'nom' => 'Révisions BD',
            'description' => 'Préparation du partiel',
        ])->assertCreated()
            ->assertJsonPath('data.type', 'PRIVE')
            ->assertJsonPath('data.nom', 'Révisions BD')
            ->assertJsonPath('data.role', 'PROPRIETAIRE')
            ->assertJsonPath('data.nb_membres', 1)
            // Sans durée choisie : durée par défaut des groupes privés (7 jours).
            ->assertJsonPath('data.duree_visibilite_heures', 168);

        $espace = Espace::findOrFail($reponse->json('data.id'));

        $this->assertSame($createur->id, $espace->created_by);
        $this->assertSame(
            RoleMembre::PROPRIETAIRE,
            Adhesion::where('espace_id', $espace->id)->where('user_id', $createur->id)->sole()->role,
        );
    }

    public function test_une_duree_null_explicite_signifie_illimitee(): void
    {
        Sanctum::actingAs($this->avecDroit());

        $this->postJson('/api/espaces', ['nom' => 'Sans limite', 'duree_visibilite_heures' => null])
            ->assertCreated()
            ->assertJsonPath('data.duree_visibilite_heures', null);
    }

    public function test_le_droit_de_publication_gestionnaires_seulement_active_le_mode_annonce(): void
    {
        Sanctum::actingAs($this->avecDroit());

        $this->postJson('/api/espaces', ['nom' => 'Annonces du projet', 'mode_annonce' => true])
            ->assertCreated()
            ->assertJsonPath('data.mode_annonce', true);

        $this->postJson('/api/espaces', ['nom' => 'Discussion libre'])
            ->assertCreated()
            ->assertJsonPath('data.mode_annonce', false);
    }

    public function test_le_nom_doit_contenir_au_moins_3_caracteres(): void
    {
        Sanctum::actingAs($this->avecDroit());

        $this->postJson('/api/espaces', ['nom' => 'BD'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nom.0', 'Le nom doit contenir au moins 3 caractères.');
    }

    public function test_une_duree_non_proposee_est_refusee(): void
    {
        Sanctum::actingAs($this->avecDroit());

        $this->postJson('/api/espaces', ['nom' => 'Durée bizarre', 'duree_visibilite_heures' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duree_visibilite_heures');
    }

    public function test_le_nom_est_obligatoire_et_limite_a_80_caracteres(): void
    {
        Sanctum::actingAs($this->avecDroit());

        $this->postJson('/api/espaces', ['nom' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nom.0', "Le nom de l'espace est obligatoire.");

        $this->postJson('/api/espaces', ['nom' => str_repeat('a', 81)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nom');
    }

    public function test_un_gestionnaire_modifie_le_nom_mais_pas_un_simple_membre(): void
    {
        $proprietaire = $this->avecDroit();
        Sanctum::actingAs($proprietaire);
        $id = $this->postJson('/api/espaces', ['nom' => 'Ancien nom'])->json('data.id');

        $membre = User::factory()->create();
        Adhesion::create(['espace_id' => $id, 'user_id' => $membre->id, 'role' => RoleMembre::MEMBRE]);

        Sanctum::actingAs($membre);
        $this->patchJson("/api/espaces/{$id}", ['nom' => 'Piraté'])->assertForbidden();

        Sanctum::actingAs($proprietaire);
        $this->patchJson("/api/espaces/{$id}", ['nom' => 'Nouveau nom'])
            ->assertOk()
            ->assertJsonPath('data.nom', 'Nouveau nom');
    }

    public function test_la_creation_exige_d_etre_connecte(): void
    {
        $this->postJson('/api/espaces', ['nom' => 'Anonyme'])->assertUnauthorized();
    }

    private function avecDroit(): User
    {
        $user = User::factory()->create();
        $user->accorderDroit(Droit::CREER_GROUPE_PRIVE);

        return $user;
    }
}
