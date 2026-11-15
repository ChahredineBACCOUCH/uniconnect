<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\User;
use App\Notifications\InvitationRecue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-121 : notifications dans l'application (canal `database`) et API des
 * notifications (liste, marquage comme lue, tout lire).
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Espace $espace;

    private User $proprietaire;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Groupe de projet']);
        $this->proprietaire = $this->adherer(RoleMembre::PROPRIETAIRE);
    }

    public function test_une_invitation_cree_une_notification_non_lue(): void
    {
        $invite = User::factory()->create();

        Sanctum::actingAs($this->proprietaire);

        $this->postJson("/api/espaces/{$this->espace->id}/invitations", [
            'user_ids' => [$invite->id],
        ])->assertCreated()
            ->assertJsonPath('creees', 1);

        $notification = $invite->notifications()->sole();

        $this->assertNull($notification->read_at);
        $this->assertSame('INVITATION', $notification->data['type']);
        $this->assertNotEmpty($notification->data['titre']);
        $this->assertNotEmpty($notification->data['texte']);
        $this->assertNotEmpty($notification->data['lien']);
    }

    public function test_la_liste_des_notifications_est_paginee(): void
    {
        $invite = User::factory()->create();
        $this->inviter($invite);

        Sanctum::actingAs($invite);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'INVITATION')
            ->assertJsonPath('data.0.lue', false)
            ->assertJsonPath('data.0.titre', 'Invitation : '.$this->espace->nom)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.par_page', 20)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.non_lues', 1);
    }

    public function test_le_filtre_des_non_lues_ignore_les_notifications_lues(): void
    {
        $invite = User::factory()->create();
        $this->inviter($invite);
        $this->notifier($invite);

        $invite->notifications()->first()->markAsRead();

        Sanctum::actingAs($invite);

        $this->getJson('/api/notifications?non_lues=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.non_lues', 1);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.non_lues', 1);
    }

    public function test_on_peut_marquer_une_notification_comme_lue(): void
    {
        $invite = User::factory()->create();
        $this->inviter($invite);
        $id = $invite->notifications()->sole()->id;

        Sanctum::actingAs($invite);

        $this->patchJson("/api/notifications/{$id}/lue")->assertNoContent();

        $notification = $invite->notifications()->sole();
        $this->assertNotNull($notification->read_at);
        $this->assertSame(0, $invite->unreadNotifications()->count());
    }

    public function test_on_ne_peut_pas_marquer_la_notification_de_autrui(): void
    {
        $invite = User::factory()->create();
        $this->inviter($invite);
        $id = $invite->notifications()->sole()->id;

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/notifications/{$id}/lue")->assertNotFound();

        $this->assertNull($invite->notifications()->sole()->read_at);
    }

    public function test_tout_lire_marque_toutes_les_notifications_comme_lues(): void
    {
        $invite = User::factory()->create();
        $this->inviter($invite);
        $this->notifier($invite);

        Sanctum::actingAs($invite);

        $this->postJson('/api/notifications/tout-lire')->assertNoContent();

        $this->assertSame(0, $invite->unreadNotifications()->count());
        $this->assertSame(2, $invite->notifications()->whereNotNull('read_at')->count());
    }

    public function test_une_nouvelle_ressource_previent_les_autres_membres(): void
    {
        $membre = $this->adherer(RoleMembre::MEMBRE);
        $chapitre = Chapitre::create([
            'espace_id' => $this->espace->id,
            'titre' => 'Chapitre 1',
            'ordre' => 0,
        ]);

        Sanctum::actingAs($this->proprietaire);

        $this->postJson("/api/espaces/{$this->espace->id}/ressources", [
            'chapitre_id' => $chapitre->id,
            'titre' => 'Cours PDF',
            'url' => 'https://exemple.tn/cours.pdf',
        ])->assertCreated();

        $notification = $membre->notifications()->sole();

        $this->assertSame('RESSOURCE', $notification->data['type']);
        $this->assertNull($notification->read_at);
        // L'auteur n'est pas prévenu de sa propre ressource.
        $this->assertSame(0, $this->proprietaire->notifications()->count());
    }

    public function test_un_membre_qui_a_desactive_les_notifications_n_est_pas_prevenu(): void
    {
        $membre = $this->adherer(RoleMembre::MEMBRE);
        Adhesion::where('espace_id', $this->espace->id)
            ->where('user_id', $membre->id)
            ->update(['notifications_actives' => false]);

        $chapitre = Chapitre::create([
            'espace_id' => $this->espace->id,
            'titre' => 'Chapitre 1',
            'ordre' => 0,
        ]);

        Sanctum::actingAs($this->proprietaire);

        $this->postJson("/api/espaces/{$this->espace->id}/ressources", [
            'chapitre_id' => $chapitre->id,
            'titre' => 'Cours PDF',
            'url' => 'https://exemple.tn/cours.pdf',
        ])->assertCreated();

        $this->assertSame(0, $membre->notifications()->count());
    }

    public function test_le_canal_database_est_refuse_quand_les_notifications_sont_desactivees(): void
    {
        $membre = $this->adherer(RoleMembre::MEMBRE);
        $notification = new InvitationRecue($this->espace, $this->proprietaire);

        // Membre avec les notifications actives : la notification part.
        $this->assertSame(['database'], $notification->via($membre));

        Adhesion::where('espace_id', $this->espace->id)
            ->where('user_id', $membre->id)
            ->update(['notifications_actives' => false]);

        // Membre qui a désactivé les notifications : rien n'est envoyé.
        $this->assertSame([], $notification->via($membre));

        // Personne pas encore membre de l'espace (invitation) : réglage par
        // défaut, la notification part.
        $this->assertSame(['database'], $notification->via(User::factory()->create()));
    }

    public function test_le_retrait_d_un_membre_le_previent(): void
    {
        $membre = $this->adherer(RoleMembre::MEMBRE);

        Sanctum::actingAs($this->proprietaire);

        $this->deleteJson("/api/espaces/{$this->espace->id}/membres/{$membre->id}")
            ->assertNoContent();

        $notification = $membre->notifications()->sole();

        $this->assertSame('RETRAIT_ESPACE', $notification->data['type']);
        $this->assertNull($notification->read_at);
    }

    /** Envoie une invitation depuis le propriétaire de l'espace. */
    private function inviter(User $invite): void
    {
        Sanctum::actingAs($this->proprietaire);

        $this->postJson("/api/espaces/{$this->espace->id}/invitations", [
            'user_ids' => [$invite->id],
        ])->assertCreated();
    }

    /** Envoie une notification d'invitation sans passer par l'API. */
    private function notifier(User $invite): void
    {
        $invite->notify(new InvitationRecue($this->espace, $this->proprietaire));
    }

    /**
     * Crée un compte et l'adhère à l'espace de test.
     */
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
