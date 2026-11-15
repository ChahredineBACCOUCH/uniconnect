<?php

namespace Tests\Feature\Espaces;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\Message;
use App\Models\PieceJointe;
use App\Models\ReglePlateforme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-136 : fichiers joints aux messages.
 *
 * Contenu facultatif quand un fichier est joint, 5 fichiers maximum, taille et
 * formats limités par les règles de la plateforme, même expiration que le message.
 */
class PiecesJointesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun fichier réel n'est écrit dans storage/ pendant les tests.
        Storage::fake('local');
    }

    public function test_un_fichier_de_trente_mo_est_refuse_avec_un_message_clair(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $fichier = UploadedFile::fake()->create('memoire.pdf', 30 * 1024, 'application/pdf');

        $response = $this->post("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Voici mon mémoire',
            'fichiers' => [$fichier],
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('fichiers.0');

        $erreurs = $response->json('errors');

        $this->assertSame(
            'Le fichier dépasse la taille maximale de 25 Mo',
            $erreurs['fichiers.0'][0],
        );

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('pieces_jointes', 0);
    }

    public function test_un_fichier_autorise_est_joint_au_message_avec_la_meme_expiration(): void
    {
        $espace = $this->creerEspace(['duree_visibilite_heures' => 7 * 24]);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $fichier = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

        $this->post("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Avec une pièce jointe',
            'fichiers' => [$fichier],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(1, 'data.pieces_jointes')
            ->assertJsonPath('data.pieces_jointes.0.nom', 'notes.pdf')
            ->assertJsonPath('data.pieces_jointes.0.mime', 'application/pdf')
            ->assertJsonPath('data.pieces_jointes.0.taille_octets', 100 * 1024);

        $message = Message::query()->firstOrFail();
        $piece = PieceJointe::query()->firstOrFail();

        $this->assertSame($message->id, $piece->message_id);
        $this->assertSame('notes.pdf', $piece->nom);
        $this->assertSame(100 * 1024, $piece->taille_octets);
        $this->assertNotNull($message->expire_le);
        $this->assertTrue($message->expire_le->equalTo($piece->expire_le));

        // Fichier rangé dans espaces/{id}/<uuid>.<extension>, jamais exposé tel quel.
        $this->assertMatchesRegularExpression(
            '#^espaces/'.$espace->id.'/[0-9a-f-]{36}\.pdf$#',
            $piece->chemin,
        );
        Storage::disk('local')->assertExists($piece->chemin);
    }

    public function test_un_message_peut_etre_envoye_avec_seulement_un_fichier(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->post("/api/espaces/{$espace->id}/messages", [
            'fichiers' => [
                UploadedFile::fake()->create('photo.png', 50, 'image/png'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.contenu', '');

        $this->assertDatabaseCount('pieces_jointes', 1);
    }

    public function test_le_contenu_reste_obligatoire_sans_fichier(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/messages", [
            'contenu' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contenu');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_plus_de_cinq_fichiers_sont_refuses(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $fichiers = array_map(
            fn (int $numero): UploadedFile => UploadedFile::fake()->create("page-{$numero}.pdf", 10, 'application/pdf'),
            range(1, 6),
        );

        $this->post("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Trop de fichiers',
            'fichiers' => $fichiers,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fichiers');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_un_format_de_fichier_non_autorise_est_refuse(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->post("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Fichier interdit',
            'fichiers' => [
                UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fichiers.0');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_les_pieces_jointes_sont_renvoyees_avec_la_discussion(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->post("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Message avec fichier',
            'fichiers' => [
                UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->getJson("/api/espaces/{$espace->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.pieces_jointes')
            ->assertJsonPath('data.0.pieces_jointes.0.nom', 'resume.pdf');
    }

    public function test_supprimer_un_message_supprime_aussi_ses_fichiers(): void
    {
        $espace = $this->creerEspace();
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $id = $this->post("/api/espaces/{$espace->id}/messages", [
            'contenu' => 'Mon TP',
            'fichiers' => [UploadedFile::fake()->create('tp.pdf', 50, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $chemin = PieceJointe::query()->where('message_id', $id)->value('chemin');
        Storage::disk('local')->assertExists($chemin);

        $this->deleteJson("/api/messages/{$id}")->assertNoContent();

        $this->assertDatabaseCount('pieces_jointes', 0);
        Storage::disk('local')->assertMissing($chemin);
    }

    public function test_les_regles_actuelles_viennent_de_la_configuration(): void
    {
        $regle = ReglePlateforme::actuelle();

        $this->assertSame(25, $regle->taille_max_mo);
        $this->assertSame(25 * 1024, $regle->taille_max_ko());
        $this->assertContains('pdf', $regle->formats_autorises);
        $this->assertContains('zip', explode(',', $regle->formats()));
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function creerEspace(array $attributs = []): Espace
    {
        return Espace::create(array_merge([
            'type' => 'PRIVE',
            'nom' => 'Groupe de révision',
        ], $attributs));
    }

    private function creerMembre(Espace $espace, RoleMembre $role = RoleMembre::MEMBRE): User
    {
        $user = User::factory()->create();

        Adhesion::create([
            'espace_id' => $espace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }
}
