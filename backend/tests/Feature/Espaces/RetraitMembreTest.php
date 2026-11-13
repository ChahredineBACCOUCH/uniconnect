<?php

namespace Tests\Feature\Espaces;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-92 (règle bloquante de l'énoncé) : un membre retiré d'un espace perd
 * immédiatement l'accès à ses messages et à ses fichiers.
 */
class RetraitMembreTest extends TestCase
{
    use RefreshDatabase;

    private Espace $espace;

    private User $proprietaire;

    private User $membre;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Révisions BD']);
        $this->proprietaire = $this->adherer(RoleMembre::PROPRIETAIRE);
        $this->membre = $this->adherer(RoleMembre::MEMBRE);

        $this->message = Message::create([
            'espace_id' => $this->espace->id,
            'auteur_id' => $this->proprietaire->id,
            'contenu' => 'Le corrigé du TP est en pièce jointe.',
        ]);
    }

    public function test_un_membre_retire_perd_immediatement_l_acces_aux_messages(): void
    {
        $id = $this->espace->id;

        // 1. Avant le retrait : le membre a accès.
        Sanctum::actingAs($this->membre);
        $this->getJson("/api/espaces/{$id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->message->id);
        $this->getJson("/api/espaces/{$id}")->assertOk();
        $this->assertContains($id, $this->idsDeMesEspaces());

        // 2. Le propriétaire le retire.
        Sanctum::actingAs($this->proprietaire);
        $this->deleteJson("/api/espaces/{$id}/membres/{$this->membre->id}")->assertNoContent();

        // 3. Dès la requête suivante : plus aucun accès.
        Sanctum::actingAs($this->membre);
        $this->getJson("/api/espaces/{$id}/messages")->assertForbidden();
        $this->getJson("/api/espaces/{$id}/messages?after_id=0")->assertForbidden(); // polling
        $this->postJson("/api/espaces/{$id}/messages", ['contenu' => 'Encore là ?'])->assertForbidden();
        $this->patchJson("/api/messages/{$this->message->id}", ['contenu' => 'Modifié'])->assertForbidden();
        $this->getJson("/api/espaces/{$id}")->assertForbidden();
        $this->getJson("/api/espaces/{$id}/membres")->assertForbidden();
        $this->postJson("/api/espaces/{$id}/ia/questions", ['question' => 'Que dit le cours ?'])->assertForbidden();

        $this->assertNotContains($id, $this->idsDeMesEspaces());
        $this->assertDatabaseMissing('adhesions', ['espace_id' => $id, 'user_id' => $this->membre->id]);
        $this->assertDatabaseCount('messages', 1); // aucun message ajouté par le membre retiré
    }

    public function test_un_membre_retire_ne_peut_plus_telecharger_les_fichiers(): void
    {
        Storage::fake();

        // La table pieces_jointes et GET /api/fichiers/{id}/telecharger arrivent au
        // sprint 2 (SCRUM-136 / SCRUM-137) : l'assertion sera complétée à ce moment-là.
        if (! Route::has('fichiers.telecharger')) {
            $this->markTestIncomplete('Route de téléchargement pas encore créée (SCRUM-137) : à compléter au sprint 2.');
        }
    }

    /** @return list<int> */
    private function idsDeMesEspaces(): array
    {
        $reponse = $this->getJson('/api/me/espaces')->assertOk();

        return collect(['organisationnels', 'prives', 'channels'])
            ->flatMap(fn (string $rubrique) => array_column($reponse->json($rubrique), 'id'))
            ->all();
    }

    private function adherer(RoleMembre $role): User
    {
        $user = User::factory()->create();
        Adhesion::create(['espace_id' => $this->espace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
