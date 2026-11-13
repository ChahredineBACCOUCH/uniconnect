<?php

namespace Tests\Feature\IA;

use App\Enums\EtatEspace;
use App\Enums\Profil;
use App\Enums\RoleMembre;
use App\IA\ClientIA;
use App\Models\Adhesion;
use App\Models\AssistantIA;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\FragmentDocument;
use App\Models\Ressource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeClientIA;
use Tests\TestCase;

/**
 * SCRUM-145 : activation de l'assistant (PATCH /api/espaces/{id}/assistant)
 * et quota de 20 questions par jour (21e question → 429).
 */
class AssistantConfigurationApiTest extends TestCase
{
    use RefreshDatabase;

    private Espace $espace;

    private User $enseignant;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // pas d'indexation automatique : les fragments sont créés à la main

        $this->espace = Espace::create(['type' => 'ORGANISATIONNEL', 'nom' => 'Bases de données']);
        $this->enseignant = $this->membre(RoleMembre::PROPRIETAIRE, Profil::ENSEIGNANT);
    }

    private function membre(RoleMembre $role, Profil $profil = Profil::ETUDIANT): User
    {
        $user = User::factory()->create(['profil' => $profil]);
        Adhesion::create(['espace_id' => $this->espace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function configurer(User $user, array $corps)
    {
        Sanctum::actingAs($user);

        return $this->patchJson("/api/espaces/{$this->espace->id}/assistant", $corps);
    }

    private function ressourceIndexee(): void
    {
        $chapitre = Chapitre::create(['espace_id' => $this->espace->id, 'titre' => 'Curseurs', 'ordre' => 1]);
        $ressource = Ressource::create([
            'chapitre_id' => $chapitre->id,
            'titre' => 'Cours',
            'chemin_fichier' => 'ressources/chap03.pdf',
            'mime' => 'application/pdf',
        ]);
        $ressource->forceFill(['indexee_ia' => true])->saveQuietly();
        FragmentDocument::create([
            'ressource_id' => $ressource->id,
            'page' => 3,
            'ordre' => 1,
            'contenu' => "Les attributs d'un curseur explicite sont %FOUND, %NOTFOUND, %ISOPEN et %ROWCOUNT.",
        ]);
    }

    public function test_l_enseignant_proprietaire_active_l_assistant(): void
    {
        $this->ressourceIndexee();

        $this->configurer($this->enseignant, ['actif' => true, 'instructions' => '  Ne donne pas les corrigés.  '])
            ->assertOk()
            ->assertExactJson([
                'actif' => true,
                'instructions' => 'Ne donne pas les corrigés.',
                'nb_documents_indexes' => 1,
            ]);

        $this->assertDatabaseHas('assistants_ia', [
            'espace_id' => $this->espace->id,
            'actif' => true,
            'instructions' => 'Ne donne pas les corrigés.',
        ]);

        // GET /espaces/{id} renvoie le nouvel état : l'onglet Assistant apparaît.
        $this->getJson("/api/espaces/{$this->espace->id}")
            ->assertOk()
            ->assertJsonPath('data.assistant.actif', true)
            ->assertJsonPath('data.assistant.instructions', 'Ne donne pas les corrigés.');
    }

    public function test_desactiver_garde_la_consigne_et_ne_cree_pas_de_doublon(): void
    {
        $this->configurer($this->enseignant, ['actif' => true, 'instructions' => 'Réponds en 3 phrases.'])->assertOk();

        $this->configurer($this->enseignant, ['actif' => false])
            ->assertOk()
            ->assertJsonPath('actif', false)
            ->assertJsonPath('instructions', 'Réponds en 3 phrases.');

        $this->assertDatabaseCount('assistants_ia', 1);

        // Une consigne vide efface la consigne.
        $this->configurer($this->enseignant, ['actif' => true, 'instructions' => '   '])
            ->assertOk()
            ->assertJsonPath('instructions', null);
    }

    public function test_un_co_gestionnaire_enseignant_peut_activer(): void
    {
        $co = $this->membre(RoleMembre::CO_GESTIONNAIRE, Profil::ENSEIGNANT);

        $this->configurer($co, ['actif' => true])->assertOk()->assertJsonPath('actif', true);
    }

    public function test_les_autres_sont_refuses(): void
    {
        $etudiant = $this->membre(RoleMembre::MEMBRE);
        $etudiantGestionnaire = $this->membre(RoleMembre::CO_GESTIONNAIRE);
        $enseignantMembre = $this->membre(RoleMembre::MEMBRE, Profil::ENSEIGNANT);
        $etranger = User::factory()->create(['profil' => Profil::ENSEIGNANT]);

        foreach ([$etudiant, $etudiantGestionnaire, $enseignantMembre, $etranger] as $user) {
            $this->configurer($user, ['actif' => true])->assertForbidden();
        }

        $this->assertDatabaseCount('assistants_ia', 0);
    }

    public function test_un_espace_archive_ne_peut_pas_etre_modifie(): void
    {
        $this->espace->update(['etat' => EtatEspace::ARCHIVE]);

        $this->configurer($this->enseignant, ['actif' => true])->assertForbidden();
    }

    public function test_les_donnees_sont_validees(): void
    {
        $this->configurer($this->enseignant, [])->assertUnprocessable()->assertJsonValidationErrors('actif');
        $this->configurer($this->enseignant, ['actif' => 'peut-être'])->assertUnprocessable()->assertJsonValidationErrors('actif');
        $this->configurer($this->enseignant, ['actif' => true, 'instructions' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.instructions.0', 'La consigne ne doit pas dépasser 1 000 caractères.');
    }

    public function test_un_visiteur_non_connecte_recoit_401(): void
    {
        $this->patchJson("/api/espaces/{$this->espace->id}/assistant", ['actif' => true])->assertUnauthorized();
    }

    public function test_20_questions_puis_la_21e_renvoie_429(): void
    {
        Carbon::setTestNow('2026-11-17 15:30:00');
        $this->ressourceIndexee();
        AssistantIA::create(['espace_id' => $this->espace->id, 'actif' => true]);
        $faux = new FakeClientIA('Les attributs sont %FOUND et %NOTFOUND [1].');
        $this->app->instance(ClientIA::class, $faux);

        $etudiant = $this->membre(RoleMembre::MEMBRE);
        Sanctum::actingAs($etudiant);
        $url = "/api/espaces/{$this->espace->id}/ia/questions";
        $question = ['question' => "Quels sont les attributs d'un curseur explicite ?"];

        for ($i = 1; $i <= 20; $i++) {
            $this->postJson($url, $question)->assertOk()->assertJsonPath('quota_restant', 20 - $i);
        }

        $this->postJson($url, $question)
            ->assertStatus(429)
            ->assertHeader('Retry-After', (string) (8 * 3600 + 30 * 60))
            ->assertJsonPath('message', 'Vous avez atteint vos 20 questions du jour. Revenez demain.')
            ->assertJsonPath('reessayer_a', Carbon::parse('2026-11-18 00:00:00')->toIso8601String());

        $this->assertSame(20, $faux->appels, "La 21e question ne doit pas appeler l'IA.");

        // Le lendemain, le quota repart à 20.
        Carbon::setTestNow('2026-11-18 08:00:00');
        $this->postJson($url, $question)->assertOk()->assertJsonPath('quota_restant', 19);
    }
}
