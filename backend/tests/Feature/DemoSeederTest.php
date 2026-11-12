<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\CompteAutorise;
use App\Models\Matiere;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SCRUM-42 : le seeder de démonstration remplit la base comme décrit dans le README,
 * peut être relancé sans doublon, et n'utilise pas Faker (absent en production).
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_les_donnees_de_demo(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame('2026/2027', AnneeUniversitaire::where('active', true)->sole()->libelle);
        $this->assertSame(3, Classe::count());
        $this->assertSame(6, Matiere::count());
        $this->assertSame(6, Classe::first()->matieres()->count());

        $this->assertSame(40, CompteAutorise::count());
        $this->assertSame(35, CompteAutorise::where('profil', Profil::ETUDIANT)->count());
        $this->assertSame(4, CompteAutorise::where('profil', Profil::ENSEIGNANT)->count());
        $this->assertSame(1, CompteAutorise::where('profil', Profil::ADMINISTRATION)->count());
        $this->assertSame(37, CompteAutorise::where('etat', EtatCompte::NON_CREE)->count());
    }

    public function test_les_trois_comptes_de_demo_sont_actives(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['etudiant', 'enseignant', 'admin'] as $prefixe) {
            $user = User::where('email', "{$prefixe}@demo.uniconnect")->sole();

            $this->assertSame(EtatCompte::ACTIF, $user->etat);
            $this->assertTrue(Hash::check('Demo1234!', $user->password));
            $this->assertSame($user->id, CompteAutorise::where('email', $user->email)->sole()->user_id);
        }

        $admin = User::where('email', 'admin@demo.uniconnect')->sole();
        $this->assertSame(count(Droit::cases()), $admin->droits()->count());

        // Droits par défaut du profil : l'étudiant et l'enseignant peuvent créer un groupe privé.
        foreach (['etudiant', 'enseignant'] as $prefixe) {
            $user = User::where('email', "{$prefixe}@demo.uniconnect")->sole();
            $this->assertSame([Droit::CREER_GROUPE_PRIVE], $user->droits->pluck('droit')->all());
        }
    }

    public function test_le_seeder_peut_etre_relance_sans_doublon(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(40, CompteAutorise::count());
        $this->assertSame(3, User::count());
        $this->assertSame(1, AnneeUniversitaire::where('active', true)->count());
    }

    public function test_le_seeder_n_utilise_pas_faker(): void
    {
        // Faker est en require-dev : sur Render (composer install --no-dev), fake() n'existe pas.
        $source = file_get_contents(database_path('seeders/DemoSeeder.php'));

        $this->assertStringNotContainsString('fake(', $source);
        $this->assertStringNotContainsString('Faker\\', $source);
    }
}
