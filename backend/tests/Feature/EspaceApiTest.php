<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-95 : informations d'un espace pour l'écran de discussion.
 */
class EspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_membre_consulte_les_informations_de_l_espace(): void
    {
        $espace = $this->creerEspace([
            'duree_visibilite_heures' => 24,
            'duree_courte_autorisee' => true,
        ]);
        $proprietaire = $this->creerMembre($espace, RoleMembre::PROPRIETAIRE);
        $membre = $this->creerMembre($espace);

        Sanctum::actingAs($membre);

        $this->getJson("/api/espaces/{$espace->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $espace->id)
            ->assertJsonPath('data.type', 'PRIVE')
            ->assertJsonPath('data.nom', 'Groupe de révision')
            ->assertJsonPath('data.etat', 'ACTIF')
            ->assertJsonPath('data.role', 'MEMBRE')
            ->assertJsonPath('data.nb_membres', 2)
            ->assertJsonPath('data.duree_visibilite_heures', 24)
            ->assertJsonPath('data.duree_courte_autorisee', true)
            ->assertJsonPath('data.mode_annonce', false)
            ->assertJsonPath('data.assistant.actif', false);

        $this->assertSame($proprietaire->id, Adhesion::query()
            ->where('espace_id', $espace->id)
            ->where('role', RoleMembre::PROPRIETAIRE->value)
            ->value('user_id'));
    }

    public function test_un_non_membre_ne_peut_pas_consulter_l_espace(): void
    {
        $espace = $this->creerEspace();
        $etranger = User::factory()->create();

        Sanctum::actingAs($etranger);

        $this->getJson("/api/espaces/{$espace->id}")->assertForbidden();
    }

    public function test_la_route_exige_une_authentification(): void
    {
        $espace = $this->creerEspace();

        $this->getJson("/api/espaces/{$espace->id}")->assertUnauthorized();
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
