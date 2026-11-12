<?php

namespace Tests\Feature\IA;

use App\Jobs\IndexerRessource;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\Ressource;
use App\Services\IndexationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SCRUM-99 : indexation des ressources pour l'Assistant IA (SCRUM-98).
 * tests/fixtures/cours-demo.pdf : 3 pages générées pour les tests (pas un vrai cours).
 */
class IndexationTest extends TestCase
{
    use RefreshDatabase;

    private Chapitre $chapitre;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        // Pas de job automatique : chaque test appelle le service lui-même.
        Queue::fake();

        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Révisions BD']);
        $this->chapitre = Chapitre::create(['espace_id' => $espace->id, 'titre' => 'Chapitre 3', 'ordre' => 1]);
    }

    private function ressourceAvec(string $fixture, string $chemin): Ressource
    {
        Storage::put($chemin, file_get_contents(base_path("tests/fixtures/{$fixture}")));

        return Ressource::create([
            'chapitre_id' => $this->chapitre->id,
            'titre' => 'Support de cours',
            'chemin_fichier' => $chemin,
            'mime' => 'application/pdf',
        ]);
    }

    public function test_un_pdf_valide_est_decoupe_en_fragments(): void
    {
        $ressource = $this->ressourceAvec('cours-demo.pdf', 'ressources/cours-demo.pdf');

        $nombre = app(IndexationService::class)->indexer($ressource);

        $fragments = $ressource->fragments()->get();
        $this->assertGreaterThan(0, $nombre);
        $this->assertCount($nombre, $fragments);
        $this->assertSame([1, 2, 3], $fragments->pluck('page')->unique()->sort()->values()->all());
        $this->assertTrue($ressource->fresh()->indexee_ia);
        $this->assertStringContainsString('ROWCOUNT', $fragments->firstWhere('page', 3)->contenu);
    }

    public function test_reindexer_remplace_les_anciens_fragments(): void
    {
        $ressource = $this->ressourceAvec('cours-demo.pdf', 'ressources/cours-demo.pdf');
        $service = app(IndexationService::class);

        $premier = $service->indexer($ressource);
        $second = $service->indexer($ressource);

        $this->assertSame($premier, $second);
        $this->assertSame($second, $ressource->fragments()->count());
    }

    public function test_un_pdf_corrompu_est_journalise_sans_exception(): void
    {
        $ressource = $this->ressourceAvec('corrompu.pdf', 'ressources/corrompu.pdf');
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $contexte) => str_contains($message, 'illisible')
                && $contexte['ressource_id'] === $ressource->id);

        $nombre = app(IndexationService::class)->indexer($ressource);

        $this->assertSame(0, $nombre);
        $this->assertFalse($ressource->fresh()->indexee_ia);
        $this->assertSame(0, $ressource->fragments()->count());
    }

    public function test_ajouter_un_pdf_lance_le_job_mais_pas_un_lien(): void
    {
        $pdf = $this->ressourceAvec('cours-demo.pdf', 'ressources/cours-demo.pdf');
        Ressource::create(['chapitre_id' => $this->chapitre->id, 'titre' => 'Vidéo', 'url' => 'https://exemple.tn/video']);

        Queue::assertPushed(IndexerRessource::class, 1);
        Queue::assertPushed(IndexerRessource::class, fn (IndexerRessource $job) => $job->ressource->is($pdf));
    }

    public function test_le_job_indexe_la_ressource(): void
    {
        $ressource = $this->ressourceAvec('cours-demo.pdf', 'ressources/cours-demo.pdf');

        (new IndexerRessource($ressource))->handle(app(IndexationService::class));

        $this->assertTrue($ressource->fresh()->indexee_ia);
        $this->assertGreaterThan(0, $ressource->fragments()->count());
    }

    public function test_decoupage_avec_chevauchement(): void
    {
        $mots = implode(' ', array_map(fn ($i) => "mot{$i}", range(1, 600)));

        $fragments = app(IndexationService::class)->decouper([1 => $mots]);

        $this->assertGreaterThan(1, count($fragments));
        foreach ($fragments as $f) {
            $this->assertLessThanOrEqual(IndexationService::TAILLE_FRAGMENT, mb_strlen($f['contenu']));
            $this->assertSame(1, $f['page']);
        }
        // Le 2e fragment commence par des mots déjà présents à la fin du 1er.
        $debutDuSecond = implode(' ', array_slice(explode(' ', $fragments[1]['contenu']), 0, 3));
        $this->assertStringContainsString($debutDuSecond, $fragments[0]['contenu']);
        $this->assertSame(range(1, count($fragments)), array_column($fragments, 'ordre'));
    }
}
