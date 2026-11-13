<?php

namespace Tests\Feature;

use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\Ressource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SCRUM-125 : une ressource est un fichier OU un lien, jamais les deux.
 * La contrainte CHECK est posée sur PostgreSQL (CI et production).
 */
class RessourceContrainteTest extends TestCase
{
    use RefreshDatabase;

    private Chapitre $chapitre;

    protected function setUp(): void
    {
        parent::setUp();

        // L'observateur de ressource lance l'indexation IA : inutile ici.
        Queue::fake();

        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Révisions BD']);
        $this->chapitre = Chapitre::create([
            'espace_id' => $espace->id,
            'titre' => 'Chapitre 1',
        ]);
    }

    public function test_les_colonnes_attendues_existent(): void
    {
        $this->assertTrue(Schema::hasColumn('chapitres', 'ordre'));

        foreach (['auteur_id', 'description', 'mime', 'taille'] as $colonne) {
            $this->assertTrue(
                Schema::hasColumn('ressources', $colonne),
                "La colonne ressources.{$colonne} est absente.",
            );
        }
    }

    public function test_un_fichier_ou_un_lien_mais_pas_les_deux(): void
    {
        $this->surPostgres();

        $this->creer(['chemin_fichier' => 'ressources/cours.pdf', 'mime' => 'application/pdf']);
        $this->creer(['url' => 'https://exemple.tn/cours']);

        $this->assertDatabaseCount('ressources', 2);

        $this->expectException(QueryException::class);
        $this->creer([
            'chemin_fichier' => 'ressources/cours.pdf',
            'url' => 'https://exemple.tn/cours',
        ]);
    }

    public function test_une_ressource_sans_fichier_ni_lien_est_refusee(): void
    {
        $this->surPostgres();

        $this->expectException(QueryException::class);
        $this->creer([]);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function creer(array $attributs): Ressource
    {
        return Ressource::create(array_merge([
            'chapitre_id' => $this->chapitre->id,
            'titre' => 'Support de cours',
        ], $attributs));
    }

    private function surPostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('La contrainte CHECK ne peut être posée que sur PostgreSQL.');
        }
    }
}
