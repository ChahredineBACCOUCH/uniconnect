<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-94 : API de discussion d'un espace.
 */
class MessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_membre_lit_les_messages_visibles_dans_l_ordre_chronologique(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        $visible = $this->creerMessage($espace, $membre, 'Message visible');
        $this->creerMessage($espace, $membre, 'Message masqué', ['masque' => true]);
        $this->creerMessage($espace, $membre, 'Message expiré', ['expire_le' => now()->subHour()]);
        $recent = $this->creerMessage($espace, $membre, 'Message encore visible', ['expire_le' => now()->addHour()]);

        Sanctum::actingAs($membre);

        $this->getJson("/api/espaces/{$espace->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.1.id', $recent->id)
            ->assertJsonPath('data.0.contenu', 'Message visible')
            ->assertJsonPath('data.0.auteur.id', $membre->id)
            ->assertJsonPath('data.0.auteur.badge', 'Étudiant')
            ->assertJsonPath('data.0.modifie', false)
            ->assertJsonPath('data.0.parent_id', null)
            ->assertJsonPath('data.0.pieces_jointes', []);
    }

    public function test_un_non_membre_ne_peut_pas_lire_les_messages(): void
    {
        $espace = $this->creerEspace();
        $etranger = User::factory()->create();

        Sanctum::actingAs($etranger);

        $this->getJson("/api/espaces/{$espace->id}/messages")
            ->assertForbidden();
    }

    public function test_la_lecture_met_a_jour_le_dernier_message_lu(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        $this->creerMessage($espace, $membre, 'Premier');
        $this->creerMessage($espace, $membre, 'Masqué', ['masque' => true]);
        $dernier = $this->creerMessage($espace, $membre, 'Dernier');

        Sanctum::actingAs($membre);

        $this->getJson("/api/espaces/{$espace->id}/messages")->assertOk();

        $adhesion = Adhesion::query()
            ->where('espace_id', $espace->id)
            ->where('user_id', $membre->id)
            ->firstOrFail();

        // Un message masqué n'est pas « lu » : le curseur reste sur le dernier message visible.
        $this->assertSame($dernier->id, $adhesion->dernier_message_lu_id);
    }

    public function test_after_id_ne_renvoie_que_les_messages_plus_recents(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        $premier = $this->creerMessage($espace, $membre, 'Un');
        $deuxieme = $this->creerMessage($espace, $membre, 'Deux');
        $troisieme = $this->creerMessage($espace, $membre, 'Trois');

        Sanctum::actingAs($membre);

        $this->getJson("/api/espaces/{$espace->id}/messages?after_id={$premier->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $deuxieme->id)
            ->assertJsonPath('data.1.id', $troisieme->id);
    }

    public function test_l_envoi_calcule_l_expiration_avec_la_duree_de_l_espace(): void
    {
        $espace = $this->creerEspace(['duree_visibilite_heures' => 24]);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $response = $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Bonjour le groupe',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.contenu', 'Bonjour le groupe')
            ->assertJsonPath('data.auteur.id', $membre->id)
            ->assertJsonPath('data.modifie', false);

        $expire = Carbon::parse($response->json('data.expire_le'));

        $this->assertTrue(
            $expire->between(now()->addHours(24)->subMinute(), now()->addHours(24)->addMinute()),
            "L'expiration doit être fixée à 24 h.",
        );
    }

    public function test_un_espace_illimite_produit_un_expire_le_null(): void
    {
        $espace = $this->creerEspace(['duree_visibilite_heures' => null]);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", ['contenu' => 'Sans expiration'])
            ->assertCreated()
            ->assertJsonPath('data.expire_le', null);
    }

    public function test_l_envoi_utilise_la_duree_plus_courte_autorisee(): void
    {
        $espace = $this->creerEspace([
            'duree_visibilite_heures' => 24,
            'duree_courte_autorisee' => true,
        ]);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $response = $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Message éphémère',
            'duree_visibilite_heures' => 2,
        ]);

        $response->assertCreated();

        $expire = Carbon::parse($response->json('data.expire_le'));

        $this->assertTrue(
            $expire->between(now()->addHours(2)->subMinute(), now()->addHours(2)->addMinute()),
            "L'expiration doit être fixée à 2 h.",
        );
    }

    public function test_l_envoi_refuse_une_duree_plus_courte_non_autorisee(): void
    {
        $espace = $this->creerEspace([
            'duree_visibilite_heures' => 24,
            'duree_courte_autorisee' => false,
        ]);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Trop court',
            'duree_visibilite_heures' => 2,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duree_visibilite_heures');
    }

    public function test_l_envoi_refuse_une_duree_plus_longue_que_celle_de_l_espace(): void
    {
        $espace = $this->creerEspace([
            'duree_visibilite_heures' => 24,
            'duree_courte_autorisee' => true,
        ]);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Trop long',
            'duree_visibilite_heures' => 48,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duree_visibilite_heures');
    }

    public function test_l_envoi_est_refuse_dans_un_espace_archive(): void
    {
        $espace = $this->creerEspace(['etat' => 'ARCHIVE']);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", ['contenu' => 'Coucou'])
            ->assertForbidden();
    }

    public function test_un_membre_simple_ne_peut_pas_publier_en_mode_annonce(): void
    {
        $espace = $this->creerEspace(['mode_annonce' => true]);
        $membre = $this->creerMembre($espace, RoleMembre::MEMBRE);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", ['contenu' => 'Annonce pirate'])
            ->assertForbidden();
    }

    public function test_un_gestionnaire_peut_publier_en_mode_annonce(): void
    {
        $espace = $this->creerEspace(['mode_annonce' => true]);
        $gestionnaire = $this->creerMembre($espace, RoleMembre::CO_GESTIONNAIRE);

        Sanctum::actingAs($gestionnaire);

        $this->postJson("/api/espaces/{$espace->id}/messages", ['contenu' => 'Annonce officielle'])
            ->assertCreated();
    }

    public function test_l_envoi_d_un_parent_hors_espace_renvoie_422(): void
    {
        $espace = $this->creerEspace();
        $autreEspace = $this->creerEspace(['nom' => 'Autre espace']);
        $membre = $this->creerMembre($espace);

        $parentAilleurs = $this->creerMessage($autreEspace, $membre, 'Ailleurs');

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Réponse impossible',
            'parent_id' => $parentAilleurs->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_l_auteur_modifie_son_message_et_modifie_le_est_rempli(): void
    {
        $espace = $this->creerEspace();
        $auteur = $this->creerMembre($espace);
        $message = $this->creerMessage($espace, $auteur, 'Version initiale');

        Sanctum::actingAs($auteur);

        $this->patchJson("/api/messages/{$message->id}", ['contenu' => 'Version corrigée'])
            ->assertOk()
            ->assertJsonPath('data.contenu', 'Version corrigée')
            ->assertJsonPath('data.modifie', true);

        $message->refresh();

        $this->assertSame('Version corrigée', $message->contenu);
        $this->assertNotNull($message->modifie_le);
    }

    public function test_un_autre_membre_ne_peut_pas_modifier_ni_supprimer_le_message(): void
    {
        $espace = $this->creerEspace();
        $auteur = $this->creerMembre($espace);
        $autre = $this->creerMembre($espace);
        $message = $this->creerMessage($espace, $auteur, 'Message protégé');

        Sanctum::actingAs($autre);

        $this->patchJson("/api/messages/{$message->id}", ['contenu' => 'Piratage'])
            ->assertForbidden();

        $this->deleteJson("/api/messages/{$message->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'contenu' => 'Message protégé']);
    }

    public function test_l_auteur_supprime_son_message(): void
    {
        $espace = $this->creerEspace();
        $auteur = $this->creerMembre($espace);
        $message = $this->creerMessage($espace, $auteur, 'À supprimer');

        Sanctum::actingAs($auteur);

        $this->deleteJson("/api/messages/{$message->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_les_routes_exigent_une_authentification(): void
    {
        $espace = $this->creerEspace();
        $message = $this->creerMessage($espace, User::factory()->create());

        $this->getJson("/api/espaces/{$espace->id}/messages")->assertUnauthorized();
        $this->postJson("/api/espaces/{$espace->id}/messages", ['contenu' => 'Test'])->assertUnauthorized();
        $this->patchJson("/api/messages/{$message->id}", ['contenu' => 'Test'])->assertUnauthorized();
        $this->deleteJson("/api/messages/{$message->id}")->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function creerEspace(array $attributs = []): Espace
    {
        return Espace::create(array_merge([
            'type' => 'PRIVE',
            'nom' => 'Groupe de révision',
        ], $attributs));
    }

    private function creerMembre(Espace $espace, RoleMembre $role = RoleMembre::MEMBRE): User
    {
        $user = User::factory()->create();

        Adhesion::create([
            'espace_id' => $espace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function creerMessage(
        Espace $espace,
        User $auteur,
        string $contenu = 'Message',
        array $attributs = [],
    ): Message {
        return Message::create(array_merge([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'contenu' => $contenu,
        ], $attributs));
    }
}
