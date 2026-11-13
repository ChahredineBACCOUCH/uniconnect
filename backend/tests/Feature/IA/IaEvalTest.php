<?php

namespace Tests\Feature\IA;

use App\IA\ClientIA;
use App\Models\Espace;
use App\Models\FragmentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeClientIA;
use Tests\TestCase;

/**
 * SCRUM-180 : php artisan ia:eval avec le PDF de démo et un faux client IA
 * (aucun appel à Gemini ni à Groq).
 */
class IaEvalTest extends TestCase
{
    use RefreshDatabase;

    private string $questions;

    private string $rapport;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $dossier = storage_path('framework/testing/ia-eval');
        @mkdir($dossier, 0777, true);
        $this->questions = $dossier.'/questions.json';
        $this->rapport = $dossier.'/rapport.md';
        @unlink($this->rapport);

        file_put_contents($this->questions, json_encode([
            // Le faux client cite [1] = l'extrait le mieux classé, la page 3 qui parle de ROWCOUNT.
            ['question' => "Que renvoie l'attribut ROWCOUNT ?", 'document' => 'cours-demo.pdf', 'page' => 3, 'hors_sujet' => false],
            ['question' => "Que renvoie l'attribut ROWCOUNT ?", 'document' => 'cours-demo.pdf', 'page' => 1, 'hors_sujet' => false],
            ['question' => 'Quel temps fera-t-il demain à Tunis ?', 'document' => null, 'page' => null, 'hors_sujet' => true],
        ]));
    }

    private function lancer(array $options = [])
    {
        return $this->artisan('ia:eval', $options + [
            'fichier' => base_path('tests/fixtures/cours-demo.pdf'),
            '--questions' => $this->questions,
            '--pause' => 0,
            '--sortie' => $this->rapport,
        ]);
    }

    public function test_evalue_le_jeu_et_publie_le_rapport(): void
    {
        $faux = new FakeClientIA('ROWCOUNT donne le nombre de lignes traitées [1].');
        $this->app->instance(ClientIA::class, $faux);

        $this->lancer()
            ->expectsOutputToContain('Sources correctes : 1 / 2 (50 %)')
            ->expectsOutputToContain('Refus corrects    : 1 / 1')
            ->assertSuccessful();

        // Hors sujet : aucun extrait trouvé, l'IA n'est pas appelée.
        $this->assertSame(2, $faux->appels);

        $rapport = file_get_contents($this->rapport);
        $this->assertStringContainsString('# Évaluation de l\'Assistant IA', $rapport);
        $this->assertStringContainsString('**1 / 2** (50 %)', $rapport);
        $this->assertStringContainsString('**1 / 1**', $rapport);
        $this->assertStringContainsString('**0 €**', $rapport);
        $this->assertStringContainsString('| 1 | Que renvoie l\'attribut ROWCOUNT ? | p. 3 | `OK` | 3 | ✅ |', $rapport);
        $this->assertStringContainsString('| 3 | Quel temps fera-t-il demain à Tunis ? | refus | `SANS_SOURCE` | - | ✅ |', $rapport);
        $this->assertStringContainsString('2400 en entrée, 160 en sortie', $rapport); // 2 réponses × (1 200 + 80)

        // Rien ne reste en base ni dans le stockage.
        $this->assertSame(0, Espace::count());
        $this->assertSame(0, FragmentDocument::count());
        $this->assertSame([], Storage::allFiles());
    }

    public function test_une_panne_de_l_ia_est_comptee_comme_erreur(): void
    {
        $this->app->instance(ClientIA::class, FakeClientIA::indisponible());

        $this->lancer(['--limite' => 1])
            ->expectsOutputToContain('Erreurs (IA indisponible) : 1')
            ->assertSuccessful();

        $this->assertStringContainsString('`ERREUR`', file_get_contents($this->rapport));
    }

    public function test_sans_sortie_aucun_rapport_n_est_ecrit(): void
    {
        $this->app->instance(ClientIA::class, new FakeClientIA('Réponse [1].'));

        $this->lancer(['--limite' => 1, '--sortie' => ''])->assertSuccessful();

        $this->assertFileDoesNotExist($this->rapport);
    }

    public function test_un_pdf_introuvable_est_refuse(): void
    {
        $this->artisan('ia:eval', ['fichier' => 'introuvable.pdf'])
            ->expectsOutputToContain('Fichier introuvable')
            ->assertFailed();
    }
}
