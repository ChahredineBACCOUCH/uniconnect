<?php

namespace Database\Factories;

use App\Models\AnneeUniversitaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnneeUniversitaire>
 */
class AnneeUniversitaireFactory extends Factory
{
    /**
     * Année universitaire inactive par défaut : une seule peut être active
     * (index unique `annee_une_seule_active`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $debut = fake()->unique()->numberBetween(2000, 2098);

        return [
            'libelle' => "{$debut}/".($debut + 1),
            'date_debut' => "{$debut}-09-01",
            'date_fin' => ($debut + 1).'-06-30',
            'active' => false,
        ];
    }

    /**
     * Année universitaire active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => true,
        ]);
    }
}
