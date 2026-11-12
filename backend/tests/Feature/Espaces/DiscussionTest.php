<?php

namespace Tests\Feature\Espaces;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-96 : règles importantes de la discussion d'un espace.
 *
 * - un non-membre ne lit ni n'envoie de message ;
 * - un message expire après la durée de visibilité de l'espace (7 jours) ;
 * - une durée illimitée laisse `expire_le` à null ;
 * - seul l'auteur peut modifier ou supprimer son message ;
 * - en mode annonce, seul le propriétaire (ou un gestionnaire) publie.
 */
class DiscussionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_non_membre_ne_peut_pas_lire_ni_envoyer_de_message(): void
    {
        $espace = $this->creerEspace();
        $etranger = User::factory()->create();

        Sanctum::actingAs($etranger);

        $this->getJson("/api/espaces/{$espace->id}/messages")
            ->assertForbidden();

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Je ne suis pas membre',
        ])->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_un_message_expire_apres_la_duree_de_visibilite_de_sept_jours(): void
    {
        $this->freezeTime();

        $espace = $this->creerEspace(['duree_visibilite_heures' => 7 * 24]);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Message valable sept jours',
        ])->assertCreated()
            ->assertJsonPath('data.contenu', 'Message valable sept jours');

        $message = Message::query()->firstOrFail();

        $this->assertNotNull($message->expire_le);
        $this->assertTrue(
            now()->addDays(7)->startOfSecond()->equalTo($message->expire_le),
            "L'expiration doit être fixée à exactement sept jours.",
        );
    }

    public function test_une_duree_illimitee_produit_un_message_sans_expiration(): void
    {
        $espace = $this->creerEspace(['duree_visibilite_heures' => null]);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Message sans expiration',
        ])->assertCreated()
            ->assertJsonPath('data.expire_le', null);

        $this->assertNull(Message::query()->firstOrFail()->expire_le);
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

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'contenu' => 'Message protégé',
        ]);
    }

    public function test_un_membre_simple_ne_peut_pas_publier_en_mode_annonce(): void
    {
        $espace = $this->creerEspace(['mode_annonce' => true]);
        $membre = $this->creerMembre($espace, RoleMembre::MEMBRE);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Annonce interdite',
        ])->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_le_proprietaire_peut_publier_en_mode_annonce(): void
    {
        $espace = $this->creerEspace(['mode_annonce' => true]);
        $proprietaire = $this->creerMembre($espace, RoleMembre::PROPRIETAIRE);

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Annonce officielle',
        ])->assertCreated()
            ->assertJsonPath('data.contenu', 'Annonce officielle')
            ->assertJsonPath('data.auteur.id', $proprietaire->id);
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
