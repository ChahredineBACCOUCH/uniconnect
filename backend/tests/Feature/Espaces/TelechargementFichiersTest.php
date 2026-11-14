<?php

namespace Tests\Feature\Espaces;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Annonce;
use App\Models\Espace;
use App\Models\Message;
use App\Models\PieceJointe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-137 : règle bloquante de l'énoncé.
 *
 * Une pièce jointe n'est téléchargeable que par un membre actuel de l'espace,
 * tant que le message n'est ni masqué ni expiré. Le contrôle est refait à
 * chaque requête : aucun lien public du bucket n'est renvoyé au front.
 */
class TelechargementFichiersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_un_membre_telecharge_la_piece_jointe_d_un_message(): void
    {
        [$espace, $membre] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'notes.pdf', 'application/pdf', 'le contenu du PDF');

        Sanctum::actingAs($membre);

        $response = $this->get("/api/fichiers/{$piece->id}/telecharger");

        $response->assertOk()
            ->assertDownload('notes.pdf')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame('le contenu du PDF', $response->streamedContent());
    }

    public function test_un_membre_affiche_une_image_avec_apercu(): void
    {
        [$espace, $membre] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'photo.png', 'image/png', 'contenu-image');

        Sanctum::actingAs($membre);

        $response = $this->get("/api/fichiers/{$piece->id}/telecharger?apercu=1");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertSame('contenu-image', $response->streamedContent());
    }

    public function test_apercu_sur_un_fichier_non_image_reste_un_telechargement(): void
    {
        [$espace, $membre] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'notes.pdf', 'application/pdf', 'pdf');

        Sanctum::actingAs($membre);

        $response = $this->get("/api/fichiers/{$piece->id}/telecharger?apercu=1");

        $response->assertOk()->assertDownload('notes.pdf');
        $this->assertStringStartsWith(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_un_non_membre_ne_peut_pas_telecharger_le_fichier(): void
    {
        [$espace] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'notes.pdf', 'application/pdf', 'secret');
        $intrus = User::factory()->create();

        Sanctum::actingAs($intrus);

        $this->getJson("/api/fichiers/{$piece->id}/telecharger")->assertForbidden();
        $this->getJson("/api/fichiers/{$piece->id}/telecharger?apercu=1")->assertForbidden();
    }

    public function test_un_message_masque_ne_livre_plus_ses_fichiers(): void
    {
        [$espace, $membre] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'notes.pdf', 'application/pdf', 'secret');

        $piece->message->update(['masque' => true]);

        Sanctum::actingAs($membre);

        $this->getJson("/api/fichiers/{$piece->id}/telecharger")->assertForbidden();
    }

    public function test_un_message_expire_ne_livre_plus_ses_fichiers(): void
    {
        [$espace, $membre] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'notes.pdf', 'application/pdf', 'secret');

        $piece->message->update(['expire_le' => now()->subMinute()]);

        Sanctum::actingAs($membre);

        $this->getJson("/api/fichiers/{$piece->id}/telecharger")->assertForbidden();
    }

    public function test_une_piece_jointe_expiree_n_est_plus_servie(): void
    {
        [$espace, $membre] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'notes.pdf', 'application/pdf', 'secret');

        $piece->update(['expire_le' => now()->subMinute()]);

        Sanctum::actingAs($membre);

        $this->getJson("/api/fichiers/{$piece->id}/telecharger")->assertForbidden();
    }

    public function test_les_fichiers_d_une_annonce_suivent_les_memes_regles(): void
    {
        [$espace, $membre] = $this->espaceEtMembre();
        $proprietaire = $this->adherer($espace, RoleMembre::PROPRIETAIRE);

        $annonce = Annonce::create([
            'espace_id' => $espace->id,
            'auteur_id' => $proprietaire->id,
            'titre' => 'Rentrée décalée',
            'contenu' => 'Voir le planning joint.',
        ]);

        $piece = $annonce->piecesJointes()->create([
            'nom' => 'planning.pdf',
            'mime' => 'application/pdf',
            'taille_octets' => 7,
            'chemin' => 'espaces/'.$espace->id.'/annonces/planning.pdf',
        ]);

        Storage::disk('local')->put($piece->chemin, 'planning');

        Sanctum::actingAs($membre);
        $this->get("/api/fichiers/{$piece->id}/telecharger")
            ->assertOk()
            ->assertDownload('planning.pdf');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/fichiers/{$piece->id}/telecharger")->assertForbidden();
    }

    public function test_un_fichier_absent_du_disque_renvoie_404(): void
    {
        [$espace, $membre] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'notes.pdf', 'application/pdf', 'secret');

        Storage::disk('local')->delete($piece->chemin);

        Sanctum::actingAs($membre);

        $this->getJson("/api/fichiers/{$piece->id}/telecharger")->assertNotFound();
    }

    public function test_un_utilisateur_non_connecte_est_refuse(): void
    {
        [$espace] = $this->espaceEtMembre();
        $piece = $this->pieceDeMessage($espace, 'notes.pdf', 'application/pdf', 'secret');

        $this->getJson("/api/fichiers/{$piece->id}/telecharger")->assertUnauthorized();
    }

    /**
     * @return array{0: Espace, 1: User}
     */
    private function espaceEtMembre(): array
    {
        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Révisions BD']);
        $membre = $this->adherer($espace, RoleMembre::MEMBRE);

        return [$espace, $membre];
    }

    private function adherer(Espace $espace, RoleMembre $role): User
    {
        $user = User::factory()->create();

        Adhesion::create([
            'espace_id' => $espace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    private function pieceDeMessage(
        Espace $espace,
        string $nom,
        string $mime,
        string $contenu,
    ): PieceJointe {
        $message = Message::create([
            'espace_id' => $espace->id,
            'contenu' => 'Message avec une pièce jointe',
        ]);

        $piece = $message->piecesJointes()->create([
            'nom' => $nom,
            'mime' => $mime,
            'taille_octets' => strlen($contenu),
            'chemin' => 'espaces/'.$espace->id.'/'.$nom,
        ]);

        Storage::disk('local')->put($piece->chemin, $contenu);

        return $piece;
    }
}
