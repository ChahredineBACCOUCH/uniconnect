<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\PieceJointe;
use App\Models\Ressource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-138 : en production, FILESYSTEM_DISK=s3 (bucket privé Supabase Storage).
 * Le disque S3 est simulé par Storage::fake('s3') : aucun appel réseau, aucune clé.
 */
class StockageS3Test extends TestCase
{
    use RefreshDatabase;

    private Espace $espace;

    private User $proprietaire;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 's3']);
        Storage::fake('s3');
        Queue::fake();

        $this->espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Bases de données']);
        $this->proprietaire = User::factory()->create();
        Adhesion::create([
            'espace_id' => $this->espace->id,
            'user_id' => $this->proprietaire->id,
            'role' => RoleMembre::PROPRIETAIRE,
        ]);
    }

    public function test_la_configuration_s3_est_privee_et_signale_les_erreurs(): void
    {
        $disque = config('filesystems.disks.s3');

        $this->assertSame('s3', $disque['driver']);
        $this->assertTrue($disque['throw']);
        $this->assertArrayNotHasKey('visibility', $disque); // jamais « public »
    }

    public function test_une_ressource_est_stockee_sur_s3_et_telechargee_par_l_api(): void
    {
        $chapitre = Chapitre::create(['espace_id' => $this->espace->id, 'titre' => 'Chapitre 1', 'ordre' => 0]);
        Sanctum::actingAs($this->proprietaire);

        $id = $this->post("/api/chapitres/{$chapitre->id}/ressources", [
            'titre' => 'Cours',
            'fichier' => UploadedFile::fake()->createWithContent('cours.pdf', '%PDF-1.4 contenu'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $chemin = Ressource::findOrFail($id)->chemin_fichier;
        Storage::disk('s3')->assertExists($chemin);
        Storage::disk('local')->assertMissing($chemin);

        // Le fichier passe par l'API (pas de redirection vers une URL du bucket).
        $reponse = $this->get("/api/ressources/{$id}/telecharger")->assertOk();
        $this->assertSame('%PDF-1.4 contenu', $reponse->streamedContent());
    }

    public function test_une_piece_jointe_de_message_est_stockee_sur_s3(): void
    {
        Sanctum::actingAs($this->proprietaire);

        $this->post("/api/espaces/{$this->espace->id}/messages", [
            'contenu' => 'Mon TP',
            'fichiers' => [UploadedFile::fake()->create('tp.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();

        Storage::disk('s3')->assertExists(PieceJointe::query()->value('chemin'));
    }
}
