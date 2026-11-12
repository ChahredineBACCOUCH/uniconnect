<?php

namespace Tests\Feature\Admin;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-74 : il ne peut jamais y avoir deux années actives, et un étudiant
 * ne peut pas utiliser les routes d'administration des années.
 */
class AnneeTest extends TestCase
{
    use RefreshDatabase;

    private function administrateur(): User
    {
        $user = User::factory()->create([
            'profil' => Profil::ADMINISTRATION,
            'etat' => EtatCompte::ACTIF,
        ]);

        $user->accorderDroit(Droit::GERER_PARAMETRAGE);

        return $user;
    }

    /**
     * Étudiant actif : la contrainte PostgreSQL exige une classe.
     */
    private function etudiant(): User
    {
        $annee = AnneeUniversitaire::factory()->create();

        $departement = Departement::create(['code' => 'INFO', 'libelle' => 'Informatique']);

        $filiere = Filiere::create([
            'code' => 'GL',
            'libelle' => 'Génie Logiciel',
            'departement_id' => $departement->id,
        ]);

        $niveau = Niveau::create(['code' => 'L2', 'libelle' => 'Licence 2']);

        $classe = Classe::create([
            'code' => 'L2-INFO-A',
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ]);

        return User::factory()->create([
            'profil' => Profil::ETUDIANT,
            'etat' => EtatCompte::ACTIF,
            'classe_id' => $classe->id,
        ]);
    }

    public function test_activer_une_annee_desactive_l_ancienne_et_il_n_en_reste_qu_une_active(): void
    {
        $anneeA = AnneeUniversitaire::factory()->active()->create([
            'libelle' => '2025/2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-06-30',
        ]);
        $anneeB = AnneeUniversitaire::factory()->create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
        ]);

        Sanctum::actingAs($this->administrateur());

        $this->postJson("/api/admin/annees/{$anneeB->id}/activer")
            ->assertOk()
            ->assertJsonPath('data.id', $anneeB->id)
            ->assertJsonPath('data.active', true);

        $this->assertFalse($anneeA->refresh()->active);
        $this->assertTrue($anneeB->refresh()->active);
        $this->assertSame(1, AnneeUniversitaire::query()->where('active', true)->count());
    }

    public function test_un_etudiant_ne_peut_pas_activer_une_annee(): void
    {
        $anneeA = AnneeUniversitaire::factory()->active()->create([
            'libelle' => '2025/2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-06-30',
        ]);
        $anneeB = AnneeUniversitaire::factory()->create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
        ]);

        Sanctum::actingAs($this->etudiant());

        $this->postJson("/api/admin/annees/{$anneeB->id}/activer")->assertForbidden();

        // La tentative refusée n'a rien changé : A est toujours la seule active.
        $this->assertTrue($anneeA->refresh()->active);
        $this->assertFalse($anneeB->refresh()->active);
        $this->assertSame(1, AnneeUniversitaire::query()->where('active', true)->count());
    }
}
