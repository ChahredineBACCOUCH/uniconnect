<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Mail\AnnoncePubliee;
use App\Models\Adhesion;
use App\Models\Annonce;
use App\Models\Espace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-123 : API des annonces d'un espace.
 */
class AnnonceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_membre_lit_les_annonces_de_la_plus_recente_a_la_plus_ancienne(): void
    {
        $espace = $this->creerEspace();
        $auteur = $this->creerMembre($espace, RoleMembre::PROPRIETAIRE);

        $ancienne = $this->creerAnnonce($espace, $auteur, 'Ancienne annonce');
        $recente = $this->creerAnnonce($espace, $auteur, 'Annonce récente');

        Sanctum::actingAs($this->creerMembre($espace));

        $this->getJson("/api/espaces/{$espace->id}/annonces")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('data.0.titre', 'Annonce récente')
            ->assertJsonPath('data.0.contenu', $recente->contenu)
            ->assertJsonPath('data.0.auteur.id', $auteur->id)
            ->assertJsonPath('data.1.id', $ancienne->id)
            ->assertJsonPath('data.1.titre', 'Ancienne annonce');
    }

    public function test_un_non_membre_ne_peut_pas_lire_les_annonces(): void
    {
        $espace = $this->creerEspace();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/espaces/{$espace->id}/annonces")
            ->assertForbidden();
    }

    public function test_un_etudiant_membre_recoit_403_en_publiant_une_annonce(): void
    {
        $espace = $this->creerEspace();
        $etudiant = $this->creerMembre($espace, RoleMembre::MEMBRE);

        Sanctum::actingAs($etudiant);

        $this->postJson("/api/espaces/{$espace->id}/annonces", [
            'titre' => 'Annonce interdite',
            'contenu' => 'Un simple membre ne peut pas publier.',
        ])->assertForbidden();

        $this->assertDatabaseCount('annonces', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_un_gestionnaire_publie_une_annonce_et_notifie_tous_les_membres(): void
    {
        Mail::fake();

        $espace = $this->creerEspace();
        $gestionnaire = $this->creerMembre($espace, RoleMembre::CO_GESTIONNAIRE);
        $membre = $this->creerMembre($espace);
        $autreMembre = $this->creerMembre($espace);

        Sanctum::actingAs($gestionnaire);

        $this->postJson("/api/espaces/{$espace->id}/annonces", [
            'titre' => 'Examen de fin de semestre',
            'contenu' => "L'examen aura lieu mardi prochain.",
        ])
            ->assertCreated()
            ->assertJsonPath('data.titre', 'Examen de fin de semestre')
            ->assertJsonPath('data.auteur.id', $gestionnaire->id)
            ->assertJsonPath('data.auteur.badge', 'Étudiant');

        $this->assertDatabaseHas('annonces', [
            'espace_id' => $espace->id,
            'auteur_id' => $gestionnaire->id,
            'notifier_email' => false,
        ]);

        // Une notification applicative est créée pour chaque membre de l'espace.
        $this->assertDatabaseCount('notifications', 3);

        $notification = $membre->notifications()->firstOrFail();

        $this->assertSame('ANNONCE', $notification->data['type']);
        $this->assertSame('Examen de fin de semestre', $notification->data['titre']);
        $this->assertSame($espace->id, $notification->data['espace_id']);

        // Case non cochée : aucun e-mail n'est mis en file d'attente.
        Mail::assertNothingQueued();
    }

    public function test_notifier_email_met_un_e_mail_en_file_d_attente_pour_chaque_membre(): void
    {
        Mail::fake();

        $espace = $this->creerEspace();
        $gestionnaire = $this->creerMembre($espace, RoleMembre::PROPRIETAIRE);
        $membre = $this->creerMembre($espace);
        $autreMembre = $this->creerMembre($espace);

        Sanctum::actingAs($gestionnaire);

        $this->postJson("/api/espaces/{$espace->id}/annonces", [
            'titre' => 'Sortie pédagogique',
            'contenu' => 'Départ à 8 h devant la faculté.',
            'notifier_email' => true,
        ])->assertCreated();

        Mail::assertQueued(AnnoncePubliee::class, 3);
        Mail::assertQueued(
            AnnoncePubliee::class,
            fn (AnnoncePubliee $mail) => $mail->hasTo($membre->email)
                && $mail->annonce->titre === 'Sortie pédagogique',
        );

        $this->assertDatabaseHas('annonces', ['notifier_email' => true]);
    }

    public function test_seul_l_auteur_supprime_son_annonce(): void
    {
        $espace = $this->creerEspace();
        $auteur = $this->creerMembre($espace, RoleMembre::CO_GESTIONNAIRE);
        $autre = $this->creerMembre($espace);
        $annonce = $this->creerAnnonce($espace, $auteur, 'À retirer');

        Sanctum::actingAs($autre);

        $this->deleteJson("/api/annonces/{$annonce->id}")
            ->assertForbidden();

        Sanctum::actingAs($auteur);

        $this->deleteJson("/api/annonces/{$annonce->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('annonces', ['id' => $annonce->id]);
    }

    public function test_les_routes_exigent_une_authentification(): void
    {
        $espace = $this->creerEspace();
        $annonce = $this->creerAnnonce($espace, User::factory()->create());

        $this->getJson("/api/espaces/{$espace->id}/annonces")->assertUnauthorized();
        $this->postJson("/api/espaces/{$espace->id}/annonces", [
            'titre' => 'Test',
            'contenu' => 'Test',
        ])->assertUnauthorized();
        $this->deleteJson("/api/annonces/{$annonce->id}")->assertUnauthorized();
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

    private function creerAnnonce(Espace $espace, User $auteur, string $titre = 'Annonce'): Annonce
    {
        return Annonce::create([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'titre' => $titre,
            'contenu' => 'Contenu de '.$titre.'.',
        ]);
    }
}
