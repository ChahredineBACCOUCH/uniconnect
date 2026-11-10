<?php

namespace Tests\Feature;

use App\Enums\EtatInvitation;
use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-88 : invitations (envoyer, accepter, refuser, annuler, expirer) et
 * recherche de personnes à inviter.
 */
class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_proprietaire_invite_et_les_doublons_sont_signales(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $dejaMembre = $this->membre($espace);
        $dejaInvite = User::factory()->create();
        $this->invitation($espace, $dejaInvite, $proprietaire);
        $nouveau1 = User::factory()->create();
        $nouveau2 = User::factory()->create();

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/espaces/{$espace->id}/invitations", [
            'user_ids' => [$nouveau1->id, $nouveau2->id, $dejaMembre->id, $dejaInvite->id],
        ])->assertCreated()
            ->assertJsonPath('creees', 2)
            ->assertJsonPath('deja_membres', [$dejaMembre->id])
            ->assertJsonPath('deja_invites', [$dejaInvite->id]);

        $invitation = Invitation::where('invite_id', $nouveau1->id)->sole();
        $this->assertSame(EtatInvitation::EN_ATTENTE, $invitation->etat);
        $this->assertSame($proprietaire->id, $invitation->invite_par_id);
        // Délai de la plateforme : 14 jours.
        $this->assertTrue($invitation->expire_le->between(now()->addDays(14)->subMinute(), now()->addDays(14)->addMinute()));
    }

    public function test_un_simple_membre_ne_peut_pas_inviter(): void
    {
        [$espace] = $this->groupePrive();
        $membre = $this->membre($espace);

        Sanctum::actingAs($membre);

        $this->postJson("/api/espaces/{$espace->id}/invitations", ['user_ids' => [User::factory()->create()->id]])
            ->assertForbidden();
    }

    public function test_on_ne_peut_pas_inviter_dans_un_groupe_organisationnel(): void
    {
        [$espace, $proprietaire] = $this->groupePrive(['type' => 'CHANNEL', 'mode_acces' => 'OUVERT']);
        $espace->update(['type' => 'ORGANISATIONNEL']);

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/espaces/{$espace->id}/invitations", ['user_ids' => [User::factory()->create()->id]])
            ->assertUnprocessable();
    }

    public function test_accepter_cree_l_adhesion_membre(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $invite = User::factory()->create();
        $invitation = $this->invitation($espace, $invite, $proprietaire);

        Sanctum::actingAs($invite);

        $this->postJson("/api/invitations/{$invitation->id}/accepter")
            ->assertOk()
            ->assertJsonPath('data.id', $espace->id)
            ->assertJsonPath('data.role', 'MEMBRE')
            ->assertJsonPath('data.nb_membres', 2);

        $this->assertSame(EtatInvitation::ACCEPTEE, $invitation->fresh()->etat);
        $this->assertSame(
            RoleMembre::MEMBRE,
            Adhesion::where('espace_id', $espace->id)->where('user_id', $invite->id)->sole()->role,
        );
    }

    public function test_refuser_passe_l_invitation_a_refusee_sans_adhesion(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $invite = User::factory()->create();
        $invitation = $this->invitation($espace, $invite, $proprietaire);

        Sanctum::actingAs($invite);

        $this->postJson("/api/invitations/{$invitation->id}/refuser")->assertNoContent();

        $this->assertSame(EtatInvitation::REFUSEE, $invitation->fresh()->etat);
        $this->assertFalse(Adhesion::where('espace_id', $espace->id)->where('user_id', $invite->id)->exists());
    }

    public function test_une_invitation_expiree_n_est_pas_acceptable(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $invite = User::factory()->create();
        $invitation = $this->invitation($espace, $invite, $proprietaire, ['expire_le' => now()->subHour()]);

        Sanctum::actingAs($invite);

        $this->postJson("/api/invitations/{$invitation->id}/accepter")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cette invitation a expiré. Demandez une nouvelle invitation.');

        $this->assertSame(EtatInvitation::EXPIREE, $invitation->fresh()->etat);
        $this->assertFalse(Adhesion::where('espace_id', $espace->id)->where('user_id', $invite->id)->exists());
    }

    public function test_seul_l_invite_peut_accepter_ou_refuser(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $invitation = $this->invitation($espace, User::factory()->create(), $proprietaire);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/invitations/{$invitation->id}/accepter")->assertForbidden();
        $this->postJson("/api/invitations/{$invitation->id}/refuser")->assertForbidden();
    }

    public function test_une_invitation_deja_traitee_ne_peut_pas_etre_acceptee(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $invite = User::factory()->create();
        $invitation = $this->invitation($espace, $invite, $proprietaire, ['etat' => EtatInvitation::REFUSEE]);

        Sanctum::actingAs($invite);

        $this->postJson("/api/invitations/{$invitation->id}/accepter")->assertUnprocessable();
    }

    public function test_l_auteur_ou_le_proprietaire_annule_mais_pas_un_autre(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $coGestionnaire = $this->membre($espace, RoleMembre::CO_GESTIONNAIRE);
        $invitationDuCo = $this->invitation($espace, User::factory()->create(), $coGestionnaire);
        $autreInvitation = $this->invitation($espace, User::factory()->create(), $coGestionnaire);

        Sanctum::actingAs($this->membre($espace));
        $this->deleteJson("/api/invitations/{$invitationDuCo->id}")->assertForbidden();

        Sanctum::actingAs($coGestionnaire);
        $this->deleteJson("/api/invitations/{$invitationDuCo->id}")->assertNoContent();
        $this->assertSame(EtatInvitation::ANNULEE, $invitationDuCo->fresh()->etat);

        Sanctum::actingAs($proprietaire);
        $this->deleteJson("/api/invitations/{$autreInvitation->id}")->assertNoContent();
        $this->assertSame(EtatInvitation::ANNULEE, $autreInvitation->fresh()->etat);
    }

    public function test_mes_invitations_ne_liste_que_les_invitations_valides(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $invite = User::factory()->create();
        $valide = $this->invitation($espace, $invite, $proprietaire);
        [$autreEspace] = $this->groupePrive();
        $this->invitation($autreEspace, $invite, $proprietaire, ['expire_le' => now()->subDay()]);

        Sanctum::actingAs($invite);

        $this->getJson('/api/me/invitations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $valide->id)
            ->assertJsonPath('data.0.espace.nom', $espace->nom)
            ->assertJsonPath('data.0.invite_par.id', $proprietaire->id);
    }

    public function test_les_gestionnaires_voient_les_invitations_en_attente_de_l_espace(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $invite = User::factory()->create();
        $this->invitation($espace, $invite, $proprietaire);

        Sanctum::actingAs($proprietaire);
        $this->getJson("/api/espaces/{$espace->id}/invitations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invite.id', $invite->id);

        Sanctum::actingAs($this->membre($espace));
        $this->getJson("/api/espaces/{$espace->id}/invitations")->assertForbidden();
    }

    public function test_la_commande_planifiee_expire_les_invitations_depassees(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $depassee = $this->invitation($espace, User::factory()->create(), $proprietaire, ['expire_le' => now()->subMinute()]);
        $valide = $this->invitation($espace, User::factory()->create(), $proprietaire);

        $this->artisan('invitations:expirer')
            ->expectsOutput('1 invitation(s) expirée(s).')
            ->assertSuccessful();

        $this->assertSame(EtatInvitation::EXPIREE, $depassee->fresh()->etat);
        $this->assertSame(EtatInvitation::EN_ATTENTE, $valide->fresh()->etat);
    }

    public function test_on_peut_reinviter_apres_une_invitation_expiree(): void
    {
        [$espace, $proprietaire] = $this->groupePrive();
        $invite = User::factory()->create();
        $this->invitation($espace, $invite, $proprietaire, ['expire_le' => now()->subDay()]);

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/espaces/{$espace->id}/invitations", ['user_ids' => [$invite->id]])
            ->assertCreated()
            ->assertJsonPath('creees', 1);
    }

    public function test_la_recherche_de_personnes_ne_renvoie_que_les_comptes_actifs(): void
    {
        $moi = User::factory()->create(['nom' => 'Ben Salah', 'prenom' => 'Yassine', 'etat' => 'ACTIF']);
        $actif = User::factory()->create(['nom' => 'Ben Ali', 'prenom' => 'Sarra', 'etat' => 'ACTIF']);
        User::factory()->create(['nom' => 'Ben Amor', 'prenom' => 'Karim', 'etat' => 'EN_ATTENTE']);
        User::factory()->create(['nom' => 'Trabelsi', 'prenom' => 'Ines', 'etat' => 'ACTIF']);

        Sanctum::actingAs($moi);

        $this->getJson('/api/utilisateurs?q=ben')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $actif->id)
            ->assertJsonMissingPath('data.0.email');

        $this->getJson('/api/utilisateurs?q=sarra ben')->assertJsonCount(1, 'data');
        $this->getJson('/api/utilisateurs?q=b')->assertUnprocessable();
    }

    /**
     * Groupe privé et son propriétaire.
     *
     * @param  array<string, mixed>  $attributs
     * @return array{0: Espace, 1: User}
     */
    private function groupePrive(array $attributs = []): array
    {
        $espace = Espace::create(array_merge(['type' => 'PRIVE', 'nom' => 'Groupe '.fake()->word()], $attributs));

        return [$espace, $this->membre($espace, RoleMembre::PROPRIETAIRE)];
    }

    private function membre(Espace $espace, RoleMembre $role = RoleMembre::MEMBRE): User
    {
        $user = User::factory()->create();
        Adhesion::create(['espace_id' => $espace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function invitation(Espace $espace, User $invite, User $par, array $attributs = []): Invitation
    {
        return Invitation::create(array_merge([
            'espace_id' => $espace->id,
            'invite_id' => $invite->id,
            'invite_par_id' => $par->id,
            'etat' => EtatInvitation::EN_ATTENTE,
            'expire_le' => now()->addDays(14),
        ], $attributs));
    }
}
