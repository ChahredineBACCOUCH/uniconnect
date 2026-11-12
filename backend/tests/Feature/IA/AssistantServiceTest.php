<?php

namespace Tests\Feature\IA;

use App\Enums\StatutEchangeIa;
use App\Exceptions\IAIndisponibleException;
use App\IA\ClientIA;
use App\Models\AssistantIA;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\FragmentDocument;
use App\Models\Ressource;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeClientIA;
use Tests\TestCase;

/**
 * SCRUM-100 : recherche des extraits, prompt et citations, avec un faux client IA.
 * Sur PostgreSQL (CI), la recherche utilise tsvector ; sur SQLite, un repli par mots.
 */
class AssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private Espace $espace;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // pas d'indexation automatique : les fragments sont créés à la main

        $this->espace = $this->espaceAvecCours([
            1 => 'Un curseur PL/SQL nomme la zone de travail allouée par Oracle pour une requête.',
            2 => 'Étapes : déclaration, ouverture avec OPEN, lecture avec FETCH, fermeture avec CLOSE.',
            3 => "Les attributs d'un curseur explicite sont %FOUND, %NOTFOUND, %ISOPEN et %ROWCOUNT.",
        ]);
    }

    private function espaceAvecCours(array $pages, string $fichier = 'chap03.pdf'): Espace
    {
        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Bases de données']);
        $chapitre = Chapitre::create(['espace_id' => $espace->id, 'titre' => 'Curseurs', 'ordre' => 1]);
        $ressource = Ressource::create([
            'chapitre_id' => $chapitre->id,
            'titre' => 'Cours',
            'chemin_fichier' => "ressources/{$fichier}",
            'mime' => 'application/pdf',
        ]);
        foreach ($pages as $page => $contenu) {
            FragmentDocument::create(['ressource_id' => $ressource->id, 'page' => $page, 'ordre' => $page, 'contenu' => $contenu]);
        }

        return $espace;
    }

    private function service(FakeClientIA $faux): AssistantService
    {
        $this->app->instance(ClientIA::class, $faux);

        return app(AssistantService::class);
    }

    public function test_reponse_avec_sources_citees(): void
    {
        $faux = new FakeClientIA('Les attributs sont %FOUND et %NOTFOUND [1]. Le curseur nomme une zone de travail [2][1].');

        $resultat = $this->service($faux)->poser($this->espace, "Quels sont les attributs d'un curseur explicite ?");

        $this->assertSame(StatutEchangeIa::OK, $resultat->statut);
        $this->assertSame(1, $faux->appels);
        $this->assertStringContainsString('JE_NE_SAIS_PAS', $faux->dernierSysteme);
        $this->assertStringContainsString('[1] (chap03.pdf, p. 3)', $faux->dernierMessage);
        $this->assertStringEndsWith("Question : Quels sont les attributs d'un curseur explicite ?", $faux->dernierMessage);

        $this->assertSame([1, 2], array_column($resultat->sources, 'n'));
        $this->assertSame(['n' => 1, 'fichier' => 'chap03.pdf', 'page' => 3], array_intersect_key($resultat->sourcesPourApi()[0], array_flip(['n', 'fichier', 'page'])));
        $this->assertSame('gemini', $resultat->ia->fournisseur);
        $this->assertGreaterThanOrEqual(0, $resultat->dureeMs);
    }

    public function test_sans_extrait_pertinent_l_ia_n_est_pas_appelee(): void
    {
        $faux = new FakeClientIA;

        $resultat = $this->service($faux)->poser($this->espace, 'Qui a gagné la Coupe du monde 2022 ?');

        $this->assertSame(StatutEchangeIa::SANS_SOURCE, $resultat->statut);
        $this->assertSame(0, $faux->appels);
        $this->assertNull($resultat->ia);
    }

    public function test_je_ne_sais_pas_donne_sans_source(): void
    {
        $resultat = $this->service(new FakeClientIA('JE_NE_SAIS_PAS'))->poser($this->espace, 'Le curseur est-il au programme du contrôle ?');

        $this->assertSame(StatutEchangeIa::SANS_SOURCE, $resultat->statut);
        $this->assertSame([], $resultat->sources);
        $this->assertNotNull($resultat->ia); // l'IA a bien été appelée : ses tokens comptent dans le quota
    }

    public function test_citations_inexistantes_ignorees_et_notation_gpt_oss(): void
    {
        $resultat = $this->service(new FakeClientIA('Voir 【1†L2】, [9] et [0].'))->poser($this->espace, 'Quels sont les attributs du curseur ?');

        $this->assertSame([1], array_column($resultat->sources, 'n'));
    }

    public function test_consigne_de_l_enseignant_ajoutee_au_prompt(): void
    {
        AssistantIA::create(['espace_id' => $this->espace->id, 'actif' => true, 'instructions' => 'Réponds en 3 phrases maximum.']);
        $faux = new FakeClientIA;

        $this->service($faux)->poser($this->espace->fresh(), 'Quels sont les attributs du curseur ?');

        $this->assertStringContainsString("Consigne de l'enseignant : Réponds en 3 phrases maximum.", $faux->dernierSysteme);
    }

    public function test_seules_les_ressources_de_l_espace_sont_utilisees(): void
    {
        $autre = $this->espaceAvecCours([7 => 'Les attributs du curseur dans un autre cours.'], 'autre.pdf');

        $extraits = app(AssistantService::class)->chercher($this->espace, 'attributs curseur');

        $this->assertNotEmpty($extraits);
        $this->assertNotContains('autre.pdf', array_column($extraits, 'fichier'));
        $this->assertSame('autre.pdf', app(AssistantService::class)->chercher($autre, 'attributs curseur')[0]['fichier']);
    }

    public function test_au_plus_six_extraits_le_plus_pertinent_en_premier(): void
    {
        $pages = [];
        foreach (range(1, 9) as $p) {
            $pages[$p] = "Page {$p} : le curseur est utilisé ici.";
        }
        $pages[10] = "Les attributs d'un curseur explicite : %FOUND et %ROWCOUNT.";
        $espace = $this->espaceAvecCours($pages, 'long.pdf');

        $extraits = app(AssistantService::class)->chercher($espace, "attributs d'un curseur explicite");

        $this->assertCount(AssistantService::NB_EXTRAITS, $extraits);
        $this->assertSame(10, $extraits[0]['page']);
        $this->assertSame(range(1, 6), array_column($extraits, 'n'));
    }

    public function test_ia_indisponible_remonte_l_exception(): void
    {
        $this->expectException(IAIndisponibleException::class);

        $this->service(FakeClientIA::indisponible())->poser($this->espace, 'Quels sont les attributs du curseur ?');
    }
}
