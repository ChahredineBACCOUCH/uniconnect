<?php

namespace Tests\Feature;

use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Models\Classe;
use App\Models\Espace;
use App\Models\User;
use App\Services\GenerationGroupesService;
use App\Services\RattachementService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SCRUM-68 / SCRUM-82 : rattachement d'un étudiant aux groupes de sa classe.
 */
class RattachementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rattache_un_etudiant_aux_groupes_de_sa_classe(): void
    {
        $this->seed(DemoSeeder::class);
        $this->app->make(GenerationGroupesService::class)->generer();

        $classe = Classe::query()->where('code', 'L2-INFO-A')->sole();

        $etudiant = User::factory()->create([
            'profil' => Profil::ETUDIANT,
            'etat' => EtatCompte::ACTIF,
            'classe_id' => $classe->id,
        ]);

        $service = $this->app->make(RattachementService::class);

        // 1 groupe de classe + 6 groupes de matière.
        $this->assertSame(7, $service->rattacher($etudiant));
        $this->assertSame(0, $service->rattacher($etudiant));

        $espaces = $etudiant->espaces()->get();

        $this->assertCount(7, $espaces);
        $this->assertTrue($espaces->every(
            fn (Espace $espace) => (int) $espace->classe_id === $classe->id
        ));
    }

    public function test_un_utilisateur_sans_classe_n_est_rattache_a_rien(): void
    {
        $this->seed(DemoSeeder::class);
        $this->app->make(GenerationGroupesService::class)->generer();

        $sansClasse = User::factory()->create([
            'profil' => Profil::ETUDIANT,
            'etat' => EtatCompte::ACTIF,
        ]);

        $this->assertSame(0, $this->app->make(RattachementService::class)->rattacher($sansClasse));
        $this->assertSame(0, $sansClasse->adhesions()->count());
    }
}
