<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\EtatEspace;
use App\Enums\Profil;
use App\Enums\TypeEspace;
use App\Models\AnneeUniversitaire;
use App\Models\Espace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-72 : gestion des années universitaires par l'administration.
 */
class AdminAnneesTest extends TestCase
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
     * @param  array<string, mixed>  $attributs
     */
    private function annee(array $attributs = []): AnneeUniversitaire
    {
        return AnneeUniversitaire::create(array_merge([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => false,
        ], $attributs));
    }

    public function test_un_invite_ne_peut_pas_acceder_aux_annees(): void
    {
        $this->getJson('/api/admin/annees')->assertUnauthorized();
    }

    public function test_un_utilisateur_sans_le_droit_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/annees')->assertForbidden();
        $this->postJson('/api/admin/annees', [
            'libelle' => '2027/2028',
            'date_debut' => '2027-09-01',
            'date_fin' => '2028-06-30',
        ])->assertForbidden();
    }

    public function test_l_administrateur_liste_les_annees_de_la_plus_recente_a_la_plus_ancienne(): void
    {
        $ancienne = $this->annee(['libelle' => '2025/2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30']);
        $recente = $this->annee(['libelle' => '2026/2027']);

        Sanctum::actingAs($this->administrateur());

        $this->getJson('/api/admin/annees')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('data.1.id', $ancienne->id)
            ->assertJsonPath('data.0.active', false);
    }

    public function test_l_administrateur_cree_une_annee(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/annees', [
            'libelle' => '2027/2028',
            'date_debut' => '2027-09-01',
            'date_fin' => '2028-06-30',
        ])
            ->assertCreated()
            ->assertJsonPath('data.libelle', '2027/2028')
            ->assertJsonPath('data.date_debut', '2027-09-01')
            ->assertJsonPath('data.date_fin', '2028-06-30')
            ->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('annees_universitaires', [
            'libelle' => '2027/2028',
            'active' => false,
        ]);
    }

    public function test_le_libelle_doit_etre_unique(): void
    {
        $this->annee(['libelle' => '2026/2027']);

        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/annees', [
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('libelle');
    }

    public function test_les_erreurs_de_validation_sont_en_francais(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/annees', ['libelle' => '2026-27'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.libelle.0', 'Le libellé doit avoir la forme « 2026/2027 ».')
            ->assertJsonPath('errors.date_debut.0', 'La date de début est obligatoire.')
            ->assertJsonPath('errors.date_fin.0', 'La date de fin est obligatoire.');
    }

    public function test_la_date_de_fin_doit_etre_posterieure_a_la_date_de_debut(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/annees', [
            'libelle' => '2027/2028',
            'date_debut' => '2028-06-30',
            'date_fin' => '2027-09-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_fin');
    }

    public function test_la_mise_a_jour_partielle_compare_les_dates_finales(): void
    {
        $annee = $this->annee();

        Sanctum::actingAs($this->administrateur());

        // Seule date_fin change : elle reste postérieure à date_debut.
        $this->patchJson("/api/admin/annees/{$annee->id}", ['date_fin' => '2027-07-31'])
            ->assertOk()
            ->assertJsonPath('data.date_fin', '2027-07-31');

        // Seule date_debut change : elle dépasserait la date_fin existante.
        $this->patchJson("/api/admin/annees/{$annee->id}", ['date_debut' => '2027-08-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_fin');
    }

    public function test_la_mise_a_jour_ignore_le_libelle_de_l_annee_courante(): void
    {
        $annee = $this->annee();

        Sanctum::actingAs($this->administrateur());

        $this->patchJson("/api/admin/annees/{$annee->id}", ['libelle' => '2026/2027'])
            ->assertOk();

        $this->assertDatabaseHas('annees_universitaires', [
            'id' => $annee->id,
            'libelle' => '2026/2027',
        ]);
    }

    public function test_l_activation_desactive_l_ancienne_annee(): void
    {
        $ancienne = $this->annee(['libelle' => '2025/2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'active' => true]);
        $nouvelle = $this->annee(['libelle' => '2026/2027', 'active' => false]);

        Sanctum::actingAs($this->administrateur());

        $this->postJson("/api/admin/annees/{$nouvelle->id}/activer")
            ->assertOk()
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('annees_universitaires', ['id' => $ancienne->id, 'active' => false]);
        $this->assertDatabaseHas('annees_universitaires', ['id' => $nouvelle->id, 'active' => true]);
        $this->assertSame(1, AnneeUniversitaire::query()->where('active', true)->count());
    }

    public function test_l_activation_archive_les_espaces_de_l_ancienne_annee_si_demande(): void
    {
        $ancienne = $this->annee(['libelle' => '2025/2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'active' => true]);
        $nouvelle = $this->annee(['libelle' => '2026/2027', 'active' => false]);

        $espaceAncien = Espace::create([
            'type' => TypeEspace::PRIVE,
            'nom' => 'Promo 2025/2026',
            'etat' => EtatEspace::ACTIF,
            'annee_id' => $ancienne->id,
        ]);

        $espaceNouveau = Espace::create([
            'type' => TypeEspace::PRIVE,
            'nom' => 'Promo 2026/2027',
            'etat' => EtatEspace::ACTIF,
            'annee_id' => $nouvelle->id,
        ]);

        Sanctum::actingAs($this->administrateur());

        $this->postJson("/api/admin/annees/{$nouvelle->id}/activer", ['archiver_precedente' => true])
            ->assertOk();

        $this->assertSame(EtatEspace::ARCHIVE, $espaceAncien->refresh()->etat);
        $this->assertSame(EtatEspace::ACTIF, $espaceNouveau->refresh()->etat);
    }

    public function test_l_activation_sans_option_n_archive_aucun_espace(): void
    {
        $ancienne = $this->annee(['libelle' => '2025/2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'active' => true]);
        $nouvelle = $this->annee(['libelle' => '2026/2027', 'active' => false]);

        $espaceAncien = Espace::create([
            'type' => TypeEspace::PRIVE,
            'nom' => 'Promo 2025/2026',
            'etat' => EtatEspace::ACTIF,
            'annee_id' => $ancienne->id,
        ]);

        Sanctum::actingAs($this->administrateur());

        $this->postJson("/api/admin/annees/{$nouvelle->id}/activer")->assertOk();

        $this->assertSame(EtatEspace::ACTIF, $espaceAncien->refresh()->etat);
    }

    public function test_activer_une_annee_inconnue_renvoie_404(): void
    {
        Sanctum::actingAs($this->administrateur());

        $this->postJson('/api/admin/annees/999/activer')->assertNotFound();
    }
}
