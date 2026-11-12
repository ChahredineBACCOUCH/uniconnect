<?php

namespace Tests\Feature\IA;

use App\Enums\RoleMembre;
use App\Enums\StatutEchangeIa;
use App\IA\ClientIA;
use App\Models\Adhesion;
use App\Models\AssistantIA;
use App\Models\Chapitre;
use App\Models\ConversationIA;
use App\Models\EchangeIA;
use App\Models\Espace;
use App\Models\FragmentDocument;
use App\Models\Ressource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeClientIA;
use Tests\TestCase;

/**
 * SCRUM-102 : POST /api/espaces/{id}/ia/questions avec un faux client IA
 * (aucun appel à Gemini ni à Groq, aucune clé nécessaire dans la CI).
 *
 * La bascule Gemini 429 → Groq (cas d) est testée avec Http::fake dans
 * ClientIATest::test_gemini_429_bascule_vers_groq.
 */
class QuestionIAApiTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = "Quels sont les attributs d'un curseur explicite ?";

    private Espace $espace;

    private User $etudiant;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // pas d'indexation automatique : les fragments sont créés à la main

        $this->espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Bases de données']);
        AssistantIA::create(['espace_id' => $this->espace->id, 'actif' => true]);

        $chapitre = Chapitre::create(['espace_id' => $this->espace->id, 'titre' => 'Curseurs', 'ordre' => 1]);
        $ressource = Ressource::create([
            'chapitre_id' => $chapitre->id,
            'titre' => 'Cours',
            'chemin_fichier' => 'ressources/chap03.pdf',
            'mime' => 'application/pdf',
        ]);
        foreach ([
            1 => 'Un curseur PL/SQL nomme la zone de travail allouée par Oracle pour une requête.',
            3 => "Les attributs d'un curseur explicite sont %FOUND, %NOTFOUND, %ISOPEN et %ROWCOUNT.",
        ] as $page => $contenu) {
            FragmentDocument::create(['ressource_id' => $ressource->id, 'page' => $page, 'ordre' => $page, 'contenu' => $contenu]);
        }

        $this->etudiant = $this->membre($this->espace);
    }

    private function membre(Espace $espace): User
    {
        $user = User::factory()->create();
        Adhesion::create(['espace_id' => $espace->id, 'user_id' => $user->id, 'role' => RoleMembre::MEMBRE]);

        return $user;
    }

    private function fauxClient(FakeClientIA $faux): FakeClientIA
    {
        $this->app->instance(ClientIA::class, $faux);

        return $faux;
    }

    private function poser(array $corps = [], ?Espace $espace = null)
    {
        Sanctum::actingAs($this->etudiant);

        return $this->postJson(
            '/api/espaces/'.($espace ?? $this->espace)->id.'/ia/questions',
            $corps + ['question' => self::QUESTION],
        );
    }

    // (a) Réponse citant [1] et [2] : 2 sources, échange et sources enregistrés.
    public function test_reponse_avec_deux_sources_enregistree(): void
    {
        $faux = $this->fauxClient(new FakeClientIA('Les attributs sont %FOUND et %NOTFOUND [1]. Un curseur nomme une zone de travail [2].'));

        $reponse = $this->poser()
            ->assertOk()
            ->assertJsonPath('statut', 'OK')
            ->assertJsonPath('quota_restant', 19)
            ->assertJsonCount(2, 'sources')
            ->assertJsonPath('sources.0.n', 1)
            ->assertJsonPath('sources.0.fichier', 'chap03.pdf')
            ->assertJsonStructure(['conversation_id', 'echange_id', 'reponse', 'statut', 'sources' => [['n', 'fichier', 'page', 'ressource_id']], 'quota_restant']);

        $this->assertSame(1, $faux->appels);

        $echange = EchangeIA::findOrFail($reponse->json('echange_id'));
        $this->assertSame(StatutEchangeIa::OK, $echange->statut);
        $this->assertSame(self::QUESTION, $echange->question);
        $this->assertSame('gemini', $echange->fournisseur);
        $this->assertSame(1200, $echange->tokens_entree);
        $this->assertCount(2, $echange->sources);

        $conversation = ConversationIA::findOrFail($reponse->json('conversation_id'));
        $this->assertSame($this->etudiant->id, $conversation->user_id);
        $this->assertSame($this->espace->id, $conversation->espace_id);
    }

    // (b) Aucun fragment pertinent : SANS_SOURCE et l'IA n'est jamais appelée.
    public function test_sans_fragment_l_ia_n_est_pas_appelee(): void
    {
        $faux = $this->fauxClient(new FakeClientIA);

        $this->poser(['question' => 'Quelle est la météo à Tunis demain ?'])
            ->assertOk()
            ->assertJsonPath('statut', 'SANS_SOURCE')
            ->assertJsonPath('reponse', null)
            ->assertJsonPath('sources', []);

        $this->assertSame(0, $faux->appels);
        $this->assertDatabaseHas('echanges_ia', ['statut' => 'SANS_SOURCE']);
    }

    // (c) Gemini et Groq en échec : 503, échange gardé en ERREUR et non compté dans le quota.
    public function test_ia_indisponible_renvoie_503(): void
    {
        $this->fauxClient(FakeClientIA::indisponible());

        $this->poser()
            ->assertStatus(503)
            ->assertJsonPath('message', "L'assistant est momentanément indisponible.");

        $this->assertDatabaseHas('echanges_ia', ['statut' => 'ERREUR', 'reponse' => null]);

        $this->fauxClient(new FakeClientIA('Réponse [1].'));
        $this->poser()->assertOk()->assertJsonPath('quota_restant', 19);
    }

    public function test_la_conversation_est_reprise_avec_conversation_id(): void
    {
        $this->fauxClient(new FakeClientIA('Réponse [1].'));

        $premiere = $this->poser()->assertOk()->json('conversation_id');

        $this->poser(['conversation_id' => $premiere])
            ->assertOk()
            ->assertJsonPath('conversation_id', $premiere)
            ->assertJsonPath('quota_restant', 18);

        $this->assertSame(2, EchangeIA::where('conversation_id', $premiere)->count());
    }

    public function test_la_conversation_d_une_autre_personne_est_refusee(): void
    {
        $faux = $this->fauxClient(new FakeClientIA);
        $autre = ConversationIA::create([
            'espace_id' => $this->espace->id,
            'user_id' => $this->membre($this->espace)->id,
        ]);

        $this->poser(['conversation_id' => $autre->id])->assertNotFound();
        $this->assertSame(0, $faux->appels);
    }

    public function test_un_non_membre_recoit_403(): void
    {
        $faux = $this->fauxClient(new FakeClientIA);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/espaces/{$this->espace->id}/ia/questions", ['question' => self::QUESTION])
            ->assertForbidden();
        $this->assertSame(0, $faux->appels);
    }

    public function test_assistant_inactif_ou_absent_renvoie_404(): void
    {
        $faux = $this->fauxClient(new FakeClientIA);

        $this->espace->assistant->update(['actif' => false]);
        $this->poser()->assertNotFound();

        $sansAssistant = Espace::create(['type' => 'PRIVE', 'nom' => 'Sans assistant']);
        Adhesion::create(['espace_id' => $sansAssistant->id, 'user_id' => $this->etudiant->id, 'role' => RoleMembre::MEMBRE]);
        $this->poser([], $sansAssistant)->assertNotFound();

        $this->assertSame(0, $faux->appels);
    }

    public function test_la_question_est_validee(): void
    {
        $this->fauxClient(new FakeClientIA);

        $this->poser(['question' => '  a '])->assertUnprocessable()->assertJsonValidationErrors('question');
        $this->poser(['question' => str_repeat('a', 1001)])->assertUnprocessable()->assertJsonValidationErrors('question');
        $this->postJson("/api/espaces/{$this->espace->id}/ia/questions", [])->assertUnprocessable();
    }

    public function test_le_quota_du_jour_renvoie_429(): void
    {
        $faux = $this->fauxClient(new FakeClientIA('Réponse [1].'));
        $conversation = ConversationIA::create(['espace_id' => $this->espace->id, 'user_id' => $this->etudiant->id]);

        // Hier : ne compte pas dans le quota d'aujourd'hui.
        $this->travel(-1)->days();
        EchangeIA::create(['conversation_id' => $conversation->id, 'question' => 'Hier', 'statut' => StatutEchangeIa::OK]);
        $this->travelBack();

        for ($i = 0; $i < 19; $i++) {
            EchangeIA::create(['conversation_id' => $conversation->id, 'question' => "Q{$i}", 'statut' => StatutEchangeIa::OK]);
        }

        $this->poser()->assertOk()->assertJsonPath('quota_restant', 0);

        $this->poser()
            ->assertStatus(429)
            ->assertJsonPath('message', 'Vous avez atteint vos 20 questions du jour. Revenez demain.');
        $this->assertSame(1, $faux->appels);
    }

    public function test_un_visiteur_non_connecte_recoit_401(): void
    {
        $this->postJson("/api/espaces/{$this->espace->id}/ia/questions", ['question' => self::QUESTION])
            ->assertUnauthorized();
    }
}
