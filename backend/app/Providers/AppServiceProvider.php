<?php

namespace App\Providers;

use App\Enums\Droit;
use App\IA\ClientIA;
use App\IA\ClientIAAvecSecours;
use App\Models\PieceJointe;
use App\Models\User;
use App\Policies\FichierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Assistant IA : Gemini, puis Groq en secours (SCRUM-100). Les tests le remplacent
        // par tests/Fakes/FakeClientIA pour ne jamais consommer le quota gratuit.
        $this->app->bind(ClientIA::class, ClientIAAvecSecours::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SCRUM-137 : FichierPolicy protège les pièces jointes (le nom de la
        // politique ne suit pas la convention PieceJointePolicy).
        Gate::policy(PieceJointe::class, FichierPolicy::class);

        // Chaque droit individuel (table `droits_utilisateur`) devient une
        // capacité utilisable dans les routes : `can:GERER_PARAMETRAGE`, etc.
        foreach (Droit::cases() as $droit) {
            Gate::define($droit->value, fn (User $user): bool => $user->possedeDroit($droit));
        }
    }
}
