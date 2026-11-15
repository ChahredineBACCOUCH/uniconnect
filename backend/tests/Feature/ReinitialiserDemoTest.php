<?php

namespace Tests\Feature;

use App\Enums\TypeEspace;
use App\Models\Espace;
use App\Models\Ressource;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * demo:reinitialiser : efface tout puis ne recharge que les données de démo.
 * DatabaseMigrations (et non RefreshDatabase) : la commande lance migrate:fresh.
 */
class ReinitialiserDemoTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    public function test_refuse_sans_la_confirmation_exacte(): void
    {
        $intrus = User::factory()->create(['email' => 'test.equipe@exemple.test']);

        $this->artisan('demo:reinitialiser')->assertFailed();
        $this->artisan('demo:reinitialiser', ['--confirmer' => 'oui'])->assertFailed();

        $this->assertModelExists($intrus);
    }

    public function test_efface_tout_et_ne_garde_que_la_demo(): void
    {
        User::factory()->create(['email' => 'test.equipe@exemple.test']);
        Espace::create(['type' => TypeEspace::PRIVE->value, 'nom' => 'tttt']);
        Storage::put('espaces/99/ressources/c1.pdf', '%PDF-1.4 test');

        $this->artisan('demo:reinitialiser', ['--confirmer' => 'EFFACER-TOUT'])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'test.equipe@exemple.test']);
        $this->assertDatabaseMissing('espaces', ['nom' => 'tttt']);
        Storage::assertMissing('espaces/99/ressources/c1.pdf');

        $this->assertDatabaseHas('users', ['email' => 'etudiant@demo.uniconnect']);
        $this->assertDatabaseHas('espaces', ['nom' => 'Bases de données']);
        foreach (Ressource::whereNotNull('chemin_fichier')->get() as $ressource) {
            Storage::assertExists($ressource->chemin_fichier);
            $this->assertTrue($ressource->indexee_ia);
        }
    }
}
