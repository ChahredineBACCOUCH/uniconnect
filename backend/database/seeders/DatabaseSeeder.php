<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'matricule' => 'ADMIN0001',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.com',
            'profil' => 'ADMINISTRATION',
            'etat' => 'ACTIF',
        ]);
    }
}
