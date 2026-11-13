<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\Ressource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * SCRUM-125 : politique d'accès aux ressources pédagogiques d'un espace.
 */
class RessourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Espace $espace;

    private Chapitre $chapitre;

    private Ressource $ressource;

    private User $proprietaire;

    private User $coGestionnaire;

    private User $membre;

    private User $etranger;

    protected function setUp(): void
    {
        parent::setUp();

        // L'observateur de ressource lance l'indexation IA : inutile ici.
        Queue::fake();

        $this->espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Révisions BD']);
        $this->chapitre = Chapitre::create([
            'espace_id' => $this->espace->id,
            'titre' => 'Chapitre 1',
            'ordre' => 1,
        ]);
        $this->ressource = Ressource::create([
            'chapitre_id' => $this->chapitre->id,
            'titre' => 'Support de cours',
            'url' => 'https://exemple.tn/cours',
        ]);

        $this->proprietaire = $this->adherer(RoleMembre::PROPRIETAIRE);
        $this->coGestionnaire = $this->adherer(RoleMembre::CO_GESTIONNAIRE);
        $this->membre = $this->adherer(RoleMembre::MEMBRE);
        $this->etranger = User::factory()->create();
    }

    public function test_tout_membre_de_l_espace_peut_voir_la_ressource(): void
    {
        $this->assertTrue(Gate::forUser($this->proprietaire)->allows('view', $this->ressource));
        $this->assertTrue(Gate::forUser($this->coGestionnaire)->allows('view', $this->ressource));
        $this->assertTrue(Gate::forUser($this->membre)->allows('view', $this->ressource));
        $this->assertFalse(Gate::forUser($this->etranger)->allows('view', $this->ressource));
    }

    public function test_seuls_les_gestionnaires_creent_la_ressource(): void
    {
        $this->assertTrue(Gate::forUser($this->proprietaire)->allows('create', [Ressource::class, $this->chapitre]));
        $this->assertTrue(Gate::forUser($this->coGestionnaire)->allows('create', [Ressource::class, $this->chapitre]));
        $this->assertFalse(Gate::forUser($this->membre)->allows('create', [Ressource::class, $this->chapitre]));
        $this->assertFalse(Gate::forUser($this->etranger)->allows('create', [Ressource::class, $this->chapitre]));
    }

    public function test_seuls_les_gestionnaires_modifient_et_suppriment(): void
    {
        foreach (['update', 'delete'] as $capacite) {
            $this->assertTrue(Gate::forUser($this->proprietaire)->allows($capacite, $this->ressource));
            $this->assertTrue(Gate::forUser($this->coGestionnaire)->allows($capacite, $this->ressource));
            $this->assertFalse(Gate::forUser($this->membre)->allows($capacite, $this->ressource));
            $this->assertFalse(Gate::forUser($this->etranger)->allows($capacite, $this->ressource));
        }
    }

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
