<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /api/me renvoie l'objet `Utilisateur` de docs/api.md : le front lit
 * `droits` pour afficher les pages et boutons réservés (auth.hasDroit()).
 */
class UtilisateurConnecteTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_utilisateur_connecte_est_renvoye_avec_ses_droits(): void
    {
        $user = User::factory()->create(['nom' => 'Diallo', 'prenom' => 'Fatou', 'profil' => 'ADMINISTRATION']);
        $user->accorderDroit(Droit::CREER_GROUPE_PRIVE);
        $user->accorderDroit(Droit::GERER_UTILISATEURS);

        Sanctum::actingAs($user);

        $reponse = $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('nom', 'Diallo')
            ->assertJsonPath('profil', 'ADMINISTRATION')
            ->assertJsonPath('classe', null);

        $this->assertEqualsCanonicalizing(['CREER_GROUPE_PRIVE', 'GERER_UTILISATEURS'], $reponse->json('droits'));
    }

    public function test_aucune_donnee_interne_n_est_exposee(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('tentatives_echouees')
            ->assertJsonMissingPath('bloque_jusqua')
            ->assertJsonMissingPath('remember_token')
            ->assertJsonMissingPath('data');
    }

    public function test_sans_jeton_la_reponse_est_401(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
