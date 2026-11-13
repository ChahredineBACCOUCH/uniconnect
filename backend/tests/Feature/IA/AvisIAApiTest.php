<?php

namespace Tests\Feature\IA;

use App\Enums\StatutEchangeIa;
use App\Models\ConversationIA;
use App\Models\EchangeIA;
use App\Models\Espace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-146 : PATCH /api/ia/echanges/{id}/avis { utile } → 204.
 */
class AvisIAApiTest extends TestCase
{
    use RefreshDatabase;

    private User $etudiant;

    private EchangeIA $echange;

    protected function setUp(): void
    {
        parent::setUp();

        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Bases de données']);
        $this->etudiant = User::factory()->create();
        $conversation = ConversationIA::create(['espace_id' => $espace->id, 'user_id' => $this->etudiant->id]);
        $this->echange = EchangeIA::create([
            'conversation_id' => $conversation->id,
            'question' => "Quels sont les attributs d'un curseur ?",
            'reponse' => 'Les attributs sont %FOUND et %NOTFOUND [1].',
            'tokens_entree' => 1200,
            'tokens_sortie' => 80,
            'fournisseur' => 'gemini',
            'statut' => StatutEchangeIa::OK,
        ]);
    }

    private function avis(mixed $utile, ?User $user = null, ?EchangeIA $echange = null)
    {
        Sanctum::actingAs($user ?? $this->etudiant);

        return $this->patchJson('/api/ia/echanges/'.($echange ?? $this->echange)->id.'/avis', ['utile' => $utile]);
    }

    public function test_l_auteur_donne_un_avis_utile_puis_le_change(): void
    {
        $this->avis(true)->assertNoContent();
        $this->assertTrue($this->echange->fresh()->avis_utile);

        $this->avis(false)->assertNoContent();
        $echange = $this->echange->fresh();
        $this->assertFalse($echange->avis_utile);

        // L'avis ne touche pas aux mesures enregistrées avec la réponse.
        $this->assertSame('gemini', $echange->fournisseur);
        $this->assertSame(1200, $echange->tokens_entree);
        $this->assertSame(80, $echange->tokens_sortie);
    }

    public function test_un_avis_est_possible_sur_une_reponse_sans_source(): void
    {
        $this->echange->update(['statut' => StatutEchangeIa::SANS_SOURCE, 'reponse' => null]);

        $this->avis(false)->assertNoContent();
        $this->assertFalse($this->echange->fresh()->avis_utile);
    }

    public function test_une_autre_personne_recoit_404(): void
    {
        $this->avis(true, User::factory()->create())->assertNotFound();
        $this->assertNull($this->echange->fresh()->avis_utile);
    }

    public function test_pas_d_avis_si_l_ia_n_a_pas_repondu(): void
    {
        $this->echange->update(['statut' => StatutEchangeIa::ERREUR, 'reponse' => null]);

        $this->avis(true)->assertUnprocessable();
        $this->assertNull($this->echange->fresh()->avis_utile);
    }

    public function test_la_valeur_est_validee(): void
    {
        $this->avis('peut-être')
            ->assertUnprocessable()
            ->assertJsonPath('errors.utile.0', 'La valeur « utile » doit être vrai ou faux.');

        Sanctum::actingAs($this->etudiant);
        $this->patchJson("/api/ia/echanges/{$this->echange->id}/avis", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('utile');
    }

    public function test_un_echange_inexistant_renvoie_404(): void
    {
        Sanctum::actingAs($this->etudiant);

        $this->patchJson('/api/ia/echanges/999999/avis', ['utile' => true])->assertNotFound();
    }

    public function test_un_visiteur_non_connecte_recoit_401(): void
    {
        $this->patchJson("/api/ia/echanges/{$this->echange->id}/avis", ['utile' => true])->assertUnauthorized();
    }
}
