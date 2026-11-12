<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Models\Adhesion;
use App\Models\AnneeUniversitaire;
use App\Models\Espace;
use App\Models\Matiere;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-82 : génération idempotente des groupes organisationnels de l'année active.
 */
class GenerationGroupesTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE_SANS_ANNEE = "Aucune année universitaire n'est active : activez une année avant de générer les groupes.";

    private function preparerDemonstration(): User
    {
        $this->seed(DemoSeeder::class);

        $admin = User::query()->where('email', 'admin@demo.uniconnect')->sole();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function etudiantDemo(): User
    {
        return User::query()->where('email', 'etudiant@demo.uniconnect')->sole();
    }

    public function test_l_apercu_liste_les_groupes_a_creer_sans_rien_ecrire(): void
    {
        $this->preparerDemonstration();

        $reponse = $this->getJson('/api/admin/groupes/apercu')
            ->assertOk()
            ->assertJsonPath('annee.libelle', '2026/2027')
            ->assertJsonPath('total', 21)
            ->assertJsonPath('deja_existants', 0)
            // 21 propriétaires (administration) + 7 adhésions de l'étudiant de L2-INFO-A.
            ->assertJsonPath('membres_a_ajouter', 28)
            ->assertJsonCount(21, 'a_creer');

        $noms = collect($reponse->json('a_creer'))->pluck('nom');

        $this->assertTrue($noms->contains('L2 Informatique A'));
        $this->assertTrue($noms->contains('Algorithmique avancée · L2 Informatique A'));
        $this->assertTrue($noms->contains('Algorithmique avancée · L3 Informatique'));

        $this->assertSame(0, Espace::count());
        $this->assertSame(0, Adhesion::count());
    }

    public function test_le_parametre_apercu_du_post_ne_cree_rien(): void
    {
        $this->preparerDemonstration();

        $this->postJson('/api/admin/groupes/generer?apercu=1')
            ->assertOk()
            ->assertJsonPath('total', 21)
            ->assertJsonCount(21, 'a_creer');

        $this->assertSame(0, Espace::count());
        $this->assertSame(0, Adhesion::count());
    }

    public function test_la_generation_cree_3_groupes_de_classe_et_18_groupes_de_matiere(): void
    {
        $admin = $this->preparerDemonstration();
        $etudiant = $this->etudiantDemo();

        $this->postJson('/api/admin/groupes/generer')
            ->assertOk()
            ->assertJsonPath('annee.libelle', '2026/2027')
            ->assertJsonPath('crees', 21)
            ->assertJsonPath('deja_existants', 0)
            // 21 propriétaires + 7 adhésions de l'étudiant de L2-INFO-A.
            ->assertJsonPath('membres_ajoutes', 28);

        $this->assertSame(21, Espace::query()->where('type', TypeEspace::ORGANISATIONNEL->value)->count());
        $this->assertSame(3, Espace::query()->whereNull('matiere_id')->count());
        $this->assertSame(18, Espace::query()->whereNotNull('matiere_id')->count());

        $this->assertSame(21, Adhesion::query()
            ->where('user_id', $admin->id)
            ->where('role', RoleMembre::PROPRIETAIRE->value)
            ->count());

        $this->assertSame(7, Adhesion::query()
            ->where('user_id', $etudiant->id)
            ->where('role', RoleMembre::MEMBRE->value)
            ->count());
    }

    public function test_relancer_la_generation_ne_cree_aucun_doublon(): void
    {
        $this->preparerDemonstration();

        $this->postJson('/api/admin/groupes/generer')->assertOk();

        $groupes = Espace::count();
        $adhesions = Adhesion::count();

        $this->postJson('/api/admin/groupes/generer')
            ->assertOk()
            ->assertJsonPath('crees', 0)
            ->assertJsonPath('deja_existants', 21)
            ->assertJsonPath('membres_ajoutes', 0);

        $this->assertSame($groupes, Espace::count());
        $this->assertSame($adhesions, Adhesion::count());

        $this->getJson('/api/admin/groupes/apercu')
            ->assertOk()
            ->assertJsonPath('total', 21)
            ->assertJsonPath('deja_existants', 21)
            ->assertJsonPath('membres_a_ajouter', 0)
            ->assertJsonCount(0, 'a_creer');
    }

    public function test_sans_annee_active_l_api_repond_422(): void
    {
        $this->preparerDemonstration();

        AnneeUniversitaire::query()->update(['active' => false]);

        $this->getJson('/api/admin/groupes/apercu')
            ->assertStatus(422)
            ->assertJsonPath('message', self::MESSAGE_SANS_ANNEE);

        $this->postJson('/api/admin/groupes/generer')
            ->assertStatus(422)
            ->assertJsonPath('message', self::MESSAGE_SANS_ANNEE);

        $this->assertSame(0, Espace::count());
    }

    public function test_l_enseignant_de_la_matiere_est_proprietaire_des_groupes_de_matiere(): void
    {
        $admin = $this->preparerDemonstration();

        $enseignant = User::query()->where('email', 'enseignant@demo.uniconnect')->sole();
        $matiere = Matiere::query()->where('code', 'INF201')->sole();
        $matiere->enseignants()->attach($enseignant->id);

        $this->postJson('/api/admin/groupes/generer')->assertOk();

        $groupes = Espace::query()
            ->where('matiere_id', $matiere->id)
            ->get();

        $this->assertCount(3, $groupes);

        foreach ($groupes as $groupe) {
            $this->assertSame(1, $groupe->adhesions()->where('role', RoleMembre::PROPRIETAIRE->value)->count());
            $this->assertTrue(
                $groupe->adhesions()
                    ->where('user_id', $enseignant->id)
                    ->where('role', RoleMembre::PROPRIETAIRE->value)
                    ->exists()
            );
            $this->assertFalse(
                $groupe->adhesions()
                    ->where('user_id', $admin->id)
                    ->where('role', RoleMembre::PROPRIETAIRE->value)
                    ->exists()
            );
        }

        // Les groupes de classe restent à l'administration.
        $groupesDeClasse = Espace::query()->whereNull('matiere_id')->pluck('id');

        $this->assertSame(3, Adhesion::query()
            ->where('user_id', $admin->id)
            ->where('role', RoleMembre::PROPRIETAIRE->value)
            ->whereIn('espace_id', $groupesDeClasse)
            ->count());
    }

    public function test_changer_l_enseignant_ne_cree_pas_un_second_proprietaire(): void
    {
        $this->preparerDemonstration();

        $matiere = Matiere::query()->where('code', 'INF202')->sole();
        $premier = User::query()->where('email', 'enseignant@demo.uniconnect')->sole();
        $matiere->enseignants()->attach($premier->id);

        $this->postJson('/api/admin/groupes/generer')->assertOk();

        $second = User::factory()->create([
            'profil' => Profil::ENSEIGNANT,
            'etat' => EtatCompte::ACTIF,
        ]);

        $matiere->enseignants()->sync([$second->id]);

        $this->postJson('/api/admin/groupes/generer')
            ->assertOk()
            ->assertJsonPath('crees', 0)
            ->assertJsonPath('membres_ajoutes', 0);

        foreach (Espace::query()->where('matiere_id', $matiere->id)->get() as $groupe) {
            $this->assertSame(1, $groupe->adhesions()->where('role', RoleMembre::PROPRIETAIRE->value)->count());
            $this->assertTrue(
                $groupe->adhesions()
                    ->where('user_id', $premier->id)
                    ->where('role', RoleMembre::PROPRIETAIRE->value)
                    ->exists()
            );
        }
    }

    public function test_un_utilisateur_sans_le_droit_ne_peut_pas_generer(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = User::query()->where('email', 'admin@demo.uniconnect')->sole();
        $admin->retirerDroit(Droit::CREER_GROUPE_ORGA);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/groupes/apercu')->assertForbidden();
        $this->postJson('/api/admin/groupes/generer')->assertForbidden();

        $this->assertSame(0, Espace::count());
    }
}
