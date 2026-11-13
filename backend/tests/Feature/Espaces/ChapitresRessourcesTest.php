<?php

namespace Tests\Feature\Espaces;

use App\Enums\RoleMembre;
use App\Jobs\IndexerRessource;
use App\Models\Adhesion;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\Ressource;
use App\Models\User;
use App\Services\IndexationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-126 : chapitres et ressources pédagogiques d'un espace.
 *
 * Un PDF ajouté ou remplacé déclenche l'indexation IA (SCRUM-98).
 */
class ChapitresRessourcesTest extends TestCase
{
    use RefreshDatabase;

    private Espace $espace;

    private User $proprietaire;

    private User $membre;

    private Chapitre $chapitre;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        $this->espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Bases de données']);
        $this->proprietaire = $this->adherer(RoleMembre::PROPRIETAIRE);
        $this->membre = $this->adherer(RoleMembre::MEMBRE);

        $this->chapitre = Chapitre::create([
            'espace_id' => $this->espace->id,
            'titre' => 'Chapitre 1',
            'ordre' => 0,
        ]);
    }

    public function test_un_membre_lit_les_chapitres_et_les_ressources_groupees(): void
    {
        $chapitre2 = Chapitre::create([
            'espace_id' => $this->espace->id,
            'titre' => 'Chapitre 2',
            'ordre' => 1,
        ]);

        $ressource1 = $this->ressource($this->chapitre, ['titre' => 'Cours PDF', 'chemin_fichier' => 'espaces/1/ressources/cours.pdf', 'mime' => 'application/pdf']);
        $ressource2 = $this->ressource($chapitre2, ['titre' => 'Vidéo', 'url' => 'https://exemple.tn/video']);

        Sanctum::actingAs($this->membre);

        $this->getJson("/api/espaces/{$this->espace->id}/chapitres")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.titre', 'Chapitre 1')
            ->assertJsonPath('data.0.ressources.0.id', $ressource1->id)
            ->assertJsonPath('data.0.ressources.0.mime', 'application/pdf')
            ->assertJsonPath('data.1.titre', 'Chapitre 2')
            ->assertJsonPath('data.1.ressources.0.id', $ressource2->id)
            ->assertJsonPath('data.1.ressources.0.url', 'https://exemple.tn/video');

        $this->getJson("/api/espaces/{$this->espace->id}/ressources")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.ressources.0.titre', 'Cours PDF')
            ->assertJsonPath('data.1.ressources.0.titre', 'Vidéo');
    }

    public function test_un_non_membre_ne_voit_ni_chapitres_ni_ressources(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/espaces/{$this->espace->id}/chapitres")->assertForbidden();
        $this->getJson("/api/espaces/{$this->espace->id}/ressources")->assertForbidden();
    }

    public function test_seuls_les_gestionnaires_gerent_les_chapitres(): void
    {
        Sanctum::actingAs($this->membre);

        $this->postJson("/api/espaces/{$this->espace->id}/chapitres", ['titre' => 'Interdit'])->assertForbidden();
        $this->patchJson("/api/chapitres/{$this->chapitre->id}", ['titre' => 'Interdit'])->assertForbidden();
        $this->deleteJson("/api/chapitres/{$this->chapitre->id}")->assertForbidden();
        $this->postJson("/api/espaces/{$this->espace->id}/chapitres/ordre", [
            'ids' => [$this->chapitre->id],
        ])->assertForbidden();

        Sanctum::actingAs($this->proprietaire);

        $response = $this->postJson("/api/espaces/{$this->espace->id}/chapitres", [
            'titre' => 'Chapitre 2',
        ])->assertCreated()
            ->assertJsonPath('data.titre', 'Chapitre 2')
            ->assertJsonPath('data.ordre', 1);

        $id = $response->json('data.id');

        $this->patchJson("/api/chapitres/{$id}", [
            'titre' => 'Chapitre renommé',
            'ordre' => 5,
        ])->assertOk()
            ->assertJsonPath('data.titre', 'Chapitre renommé')
            ->assertJsonPath('data.ordre', 5);

        $this->deleteJson("/api/chapitres/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('chapitres', ['id' => $id]);
    }

    public function test_reordonner_les_chapitres(): void
    {
        $chapitre2 = Chapitre::create(['espace_id' => $this->espace->id, 'titre' => 'Chapitre 2', 'ordre' => 1]);
        $chapitre3 = Chapitre::create(['espace_id' => $this->espace->id, 'titre' => 'Chapitre 3', 'ordre' => 2]);

        Sanctum::actingAs($this->proprietaire);

        $this->postJson("/api/espaces/{$this->espace->id}/chapitres/ordre", [
            'ids' => [$chapitre3->id, $this->chapitre->id, $chapitre2->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $chapitre3->id)
            ->assertJsonPath('data.0.ordre', 0)
            ->assertJsonPath('data.1.id', $this->chapitre->id)
            ->assertJsonPath('data.1.ordre', 1)
            ->assertJsonPath('data.2.id', $chapitre2->id)
            ->assertJsonPath('data.2.ordre', 2);

        $this->assertSame(
            [$chapitre3->id, $this->chapitre->id, $chapitre2->id],
            $this->espace->chapitres()->pluck('id')->all(),
        );
    }

    public function test_reordonner_refuse_un_chapitre_d_un_autre_espace(): void
    {
        $autreEspace = Espace::create(['type' => 'PRIVE', 'nom' => 'Autre']);
        $chapitreAilleurs = Chapitre::create([
            'espace_id' => $autreEspace->id,
            'titre' => 'Ailleurs',
        ]);

        Sanctum::actingAs($this->proprietaire);

        $this->postJson("/api/espaces/{$this->espace->id}/chapitres/ordre", [
            'ids' => [$this->chapitre->id, $chapitreAilleurs->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids.1');
    }

    public function test_ajouter_une_ressource_fichier_ou_lien(): void
    {
        Sanctum::actingAs($this->proprietaire);

        $pdf = UploadedFile::fake()->create('cours.pdf', 100, 'application/pdf');

        $response = $this->post(
            "/api/chapitres/{$this->chapitre->id}/ressources",
            ['titre' => 'Support de cours', 'fichier' => $pdf],
            ['Accept' => 'application/json'],
        );

        $response->assertCreated()
            ->assertJsonPath('data.titre', 'Support de cours')
            ->assertJsonPath('data.mime', 'application/pdf')
            ->assertJsonPath('data.url', null)
            ->assertJsonPath('data.taille', 100 * 1024);

        $ressource = Ressource::query()->firstOrFail();

        $this->assertMatchesRegularExpression(
            '#^espaces/'.$this->espace->id.'/ressources/[A-Za-z0-9]+\.pdf$#',
            $ressource->chemin_fichier,
        );
        Storage::disk('local')->assertExists($ressource->chemin_fichier);
        $this->assertSame($this->proprietaire->id, $ressource->auteur_id);

        $this->postJson("/api/espaces/{$this->espace->id}/ressources", [
            'chapitre_id' => $this->chapitre->id,
            'titre' => 'Vidéo du cours',
            'url' => 'https://exemple.tn/video',
        ])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://exemple.tn/video')
            ->assertJsonPath('data.mime', null)
            ->assertJsonPath('data.taille', null);

        $this->postJson("/api/espaces/{$this->espace->id}/ressources", [
            'chapitre_id' => $this->chapitre->id,
            'titre' => 'Sans contenu',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fichier');

        $this->post("/api/espaces/{$this->espace->id}/ressources", [
            'chapitre_id' => $this->chapitre->id,
            'titre' => 'Les deux',
            'fichier' => UploadedFile::fake()->create('autre.pdf', 10, 'application/pdf'),
            'url' => 'https://exemple.tn/autre',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fichier', 'url']);
    }

    public function test_un_membre_ne_peut_pas_ajouter_une_ressource(): void
    {
        Sanctum::actingAs($this->membre);

        $this->postJson("/api/espaces/{$this->espace->id}/ressources", [
            'chapitre_id' => $this->chapitre->id,
            'titre' => 'Interdit',
            'url' => 'https://exemple.tn/interdit',
        ])->assertForbidden();
    }

    public function test_modifier_deplacer_et_supprimer_une_ressource(): void
    {
        Storage::disk('local')->put('espaces/'.$this->espace->id.'/ressources/ancien.pdf', 'ancien');
        $ressource = $this->ressource($this->chapitre, [
            'titre' => 'Ancien support',
            'chemin_fichier' => 'espaces/'.$this->espace->id.'/ressources/ancien.pdf',
            'mime' => 'application/pdf',
            'taille' => 6,
        ]);
        $chapitre2 = Chapitre::create(['espace_id' => $this->espace->id, 'titre' => 'Chapitre 2']);

        Sanctum::actingAs($this->proprietaire);

        $nouveauPdf = UploadedFile::fake()->create('nouveau.pdf', 200, 'application/pdf');

        $this->patch(
            "/api/ressources/{$ressource->id}",
            ['titre' => 'Nouveau support', 'fichier' => $nouveauPdf],
            ['Accept' => 'application/json'],
        )
            ->assertOk()
            ->assertJsonPath('data.titre', 'Nouveau support')
            ->assertJsonPath('data.mime', 'application/pdf')
            ->assertJsonPath('data.taille', 200 * 1024);

        $ressource->refresh();
        Storage::disk('local')->assertMissing('espaces/'.$this->espace->id.'/ressources/ancien.pdf');
        Storage::disk('local')->assertExists($ressource->chemin_fichier);

        $this->patchJson("/api/ressources/{$ressource->id}/deplacer", [
            'chapitre_id' => $chapitre2->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.chapitre_id', $chapitre2->id);

        $autreEspace = Espace::create(['type' => 'PRIVE', 'nom' => 'Autre']);
        $chapitreAilleurs = Chapitre::create(['espace_id' => $autreEspace->id, 'titre' => 'Ailleurs']);

        $this->patchJson("/api/ressources/{$ressource->id}/deplacer", [
            'chapitre_id' => $chapitreAilleurs->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chapitre_id');

        $chemin = $ressource->fresh()->chemin_fichier;

        $this->deleteJson("/api/ressources/{$ressource->id}")->assertNoContent();

        $this->assertDatabaseMissing('ressources', ['id' => $ressource->id]);
        Storage::disk('local')->assertMissing($chemin);
    }

    public function test_un_membre_ne_peut_pas_modifier_ni_supprimer_une_ressource(): void
    {
        $ressource = $this->ressource($this->chapitre, ['titre' => 'Protégée', 'url' => 'https://exemple.tn/protegee']);

        Sanctum::actingAs($this->membre);

        $this->patchJson("/api/ressources/{$ressource->id}", ['titre' => 'Piratage'])->assertForbidden();
        $this->deleteJson("/api/ressources/{$ressource->id}")->assertForbidden();
        $this->patchJson("/api/ressources/{$ressource->id}/deplacer", [
            'chapitre_id' => $this->chapitre->id,
        ])->assertForbidden();
    }

    public function test_ajouter_un_pdf_declenche_l_indexation_ia(): void
    {
        Sanctum::actingAs($this->proprietaire);

        $pdf = UploadedFile::fake()->createWithContent(
            'cours.pdf',
            file_get_contents(base_path('tests/fixtures/cours-demo.pdf')),
        );

        $response = $this->post(
            "/api/chapitres/{$this->chapitre->id}/ressources",
            ['titre' => 'Cours indexé', 'fichier' => $pdf],
            ['Accept' => 'application/json'],
        )->assertCreated();

        $ressource = Ressource::query()->findOrFail($response->json('data.id'));

        $job = null;
        Queue::assertPushed(IndexerRessource::class, function (IndexerRessource $pushed) use (&$job, $ressource): bool {
            $job = $pushed;

            return $pushed->ressource->is($ressource);
        });

        $this->assertNotNull($job);

        // Le worker exécute le job : le PDF est découpé et indexee_ia passe à true.
        $job->handle(app(IndexationService::class));

        $this->assertTrue($ressource->fresh()->indexee_ia);
        $this->assertGreaterThan(0, $ressource->fragments()->count());
    }

    public function test_remplacer_un_pdf_relance_l_indexation(): void
    {
        Storage::disk('local')->put('espaces/'.$this->espace->id.'/ressources/ancien.pdf', 'ancien');
        $ressource = $this->ressource($this->chapitre, [
            'titre' => 'Ancien PDF',
            'chemin_fichier' => 'espaces/'.$this->espace->id.'/ressources/ancien.pdf',
            'mime' => 'application/pdf',
        ]);

        Queue::fake();

        Sanctum::actingAs($this->proprietaire);

        $pdf = UploadedFile::fake()->createWithContent(
            'nouveau.pdf',
            file_get_contents(base_path('tests/fixtures/cours-demo.pdf')),
        );

        $this->patch(
            "/api/ressources/{$ressource->id}",
            ['fichier' => $pdf],
            ['Accept' => 'application/json'],
        )->assertOk();

        $job = null;
        Queue::assertPushed(IndexerRessource::class, function (IndexerRessource $pushed) use (&$job): bool {
            $job = $pushed;

            return true;
        });

        $this->assertNotNull($job);

        $job->handle(app(IndexationService::class));

        $this->assertTrue($ressource->fresh()->indexee_ia);
        $this->assertGreaterThan(0, $ressource->fragments()->count());
    }

    public function test_remplacer_un_fichier_par_un_lien_supprime_les_fragments_ia(): void
    {
        Storage::disk('local')->put('espaces/'.$this->espace->id.'/ressources/ancien.pdf', 'ancien');
        $ressource = $this->ressource($this->chapitre, [
            'titre' => 'Ancien PDF',
            'chemin_fichier' => 'espaces/'.$this->espace->id.'/ressources/ancien.pdf',
            'mime' => 'application/pdf',
            'indexee_ia' => true,
        ]);

        $ressource->fragments()->create(['page' => 1, 'ordre' => 0, 'contenu' => 'Extrait de l’ancien PDF']);

        Sanctum::actingAs($this->proprietaire);

        $this->patchJson("/api/ressources/{$ressource->id}", [
            'url' => 'https://exemple.tn/nouveau',
        ])
            ->assertOk()
            ->assertJsonPath('data.url', 'https://exemple.tn/nouveau')
            ->assertJsonPath('data.mime', null)
            ->assertJsonPath('data.indexee_ia', false);

        $ressource->refresh();

        $this->assertFalse($ressource->indexee_ia);
        $this->assertSame(0, $ressource->fragments()->count());
        Storage::disk('local')->assertMissing('espaces/'.$this->espace->id.'/ressources/ancien.pdf');
    }

    public function test_telecharger_une_ressource_fichier_ou_lien(): void
    {
        Storage::disk('local')->put('espaces/'.$this->espace->id.'/ressources/cours.pdf', 'contenu');
        $fichier = $this->ressource($this->chapitre, [
            'titre' => 'PDF',
            'chemin_fichier' => 'espaces/'.$this->espace->id.'/ressources/cours.pdf',
            'mime' => 'application/pdf',
        ]);
        $lien = $this->ressource($this->chapitre, ['titre' => 'Lien', 'url' => 'https://exemple.tn']);

        Sanctum::actingAs($this->membre);

        $this->get("/api/ressources/{$fichier->id}/telecharger")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->getJson("/api/ressources/{$lien->id}/telecharger")->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/ressources/{$fichier->id}/telecharger")->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function ressource(Chapitre $chapitre, array $attributs = []): Ressource
    {
        $attributs = array_merge([
            'chapitre_id' => $chapitre->id,
            'titre' => 'Ressource',
        ], $attributs);

        // Une ressource est un fichier OU un lien (contrainte en base).
        if (! isset($attributs['chemin_fichier']) && ! isset($attributs['url'])) {
            $attributs['url'] = 'https://exemple.tn';
        }

        return Ressource::create($attributs);
    }

    private function adherer(RoleMembre $role): User
    {
        $user = User::factory()->create();

        Adhesion::create([
            'espace_id' => $this->espace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }
}
