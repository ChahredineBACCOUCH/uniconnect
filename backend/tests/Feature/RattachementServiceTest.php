<?php

namespace Tests\Feature;

use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Classe;
use App\Models\Espace;
use App\Models\Matiere;
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
        // 1 groupe de classe + 6 groupes de matière (toutes classes confondues).
        $this->assertSame(1, $espaces->where('classe_id', $classe->id)->count());
        $this->assertSame(6, $espaces
            ->whereNull('classe_id')
            ->whereNotNull('matiere_id')
            ->count());
        $this->assertTrue($espaces->every(
            fn (Espace $espace) => (int) $espace->annee_id === (int) $classe->annee_id
        ));
    }

    public function test_un_enseignant_responsable_devient_proprietaire_des_groupes_de_ses_matieres(): void
    {
        $this->seed(DemoSeeder::class);

        $matieres = Matiere::query()
            ->whereIn('code', ['INF201', 'INF202'])
            ->orderBy('code')
            ->get();

        $enseignant = User::factory()->create([
            'profil' => Profil::ENSEIGNANT,
            'etat' => EtatCompte::EN_ATTENTE,
        ]);
        $enseignant->matieres()->attach($matieres->modelKeys());

        // Groupes générés avant l'activation : l'administration est propriétaire
        // par défaut, car l'enseignant n'est pas encore actif.
        $this->app->make(GenerationGroupesService::class)->generer();

        $enseignant->update(['etat' => EtatCompte::ACTIF]);

        $service = $this->app->make(RattachementService::class);

        // Une adhésion par matière, puis plus rien : idempotent.
        $this->assertSame(2, $service->rattacher($enseignant));
        $this->assertSame(0, $service->rattacher($enseignant));

        $espaces = $enseignant->espaces()->get();

        $this->assertCount(2, $espaces);

        foreach ($espaces as $espace) {
            $this->assertSame(RoleMembre::PROPRIETAIRE->value, (string) $espace->pivot->role);
            $this->assertSame(1, Adhesion::query()
                ->where('espace_id', $espace->id)
                ->where('role', RoleMembre::PROPRIETAIRE->value)
                ->count());
            $this->assertSame(1, Adhesion::query()
                ->where('espace_id', $espace->id)
                ->where('role', RoleMembre::CO_GESTIONNAIRE->value)
                ->count());
        }

        // L'administration garde l'accès aux groupes : elle devient co-gestionnaire.
        $admin = User::query()->where('email', 'admin@demo.uniconnect')->sole();

        $this->assertSame(2, $admin->adhesions()
            ->where('role', RoleMembre::CO_GESTIONNAIRE->value)
            ->count());
    }

    public function test_un_utilisateur_sans_classe_n_est_rattache_a_rien(): void
    {
        $this->seed(DemoSeeder::class);
        $this->app->make(GenerationGroupesService::class)->generer();

        // Un étudiant actif doit obligatoirement avoir une classe (contrainte
        // PostgreSQL) : on utilise donc un enseignant, qui n'en a pas.
        $sansClasse = User::factory()->create([
            'profil' => Profil::ENSEIGNANT,
            'etat' => EtatCompte::ACTIF,
        ]);

        $this->assertSame(0, $this->app->make(RattachementService::class)->rattacher($sansClasse));
        $this->assertSame(0, $sansClasse->adhesions()->count());
    }
}
