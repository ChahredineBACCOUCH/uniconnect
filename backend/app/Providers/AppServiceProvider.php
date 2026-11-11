<?php

namespace App\Providers;

use App\IA\ClientIA;
use App\IA\ClientIAAvecSecours;
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
        //
    }
}
