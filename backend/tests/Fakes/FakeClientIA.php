<?php

namespace Tests\Fakes;

use App\Exceptions\IAIndisponibleException;
use App\IA\ClientIA;
use App\IA\ReponseIA;

/**
 * Faux client IA pour les tests : aucun appel à Gemini ni à Groq, aucun quota consommé.
 *
 *   $this->app->instance(ClientIA::class, $faux = new FakeClientIA('Réponse [1] et [2].'));
 *   …
 *   $this->assertSame(1, $faux->appels);
 *
 * Passer une exception (ex. new IAIndisponibleException) pour simuler une panne.
 */
class FakeClientIA implements ClientIA
{
    public int $appels = 0;

    public ?string $dernierSysteme = null;

    public ?string $dernierMessage = null;

    public function __construct(
        private string|\Throwable $reponse = 'Réponse de test [1].',
        private string $fournisseur = 'gemini',
    ) {}

    public function repondre(string $systeme, string $message): ReponseIA
    {
        $this->appels++;
        $this->dernierSysteme = $systeme;
        $this->dernierMessage = $message;

        if ($this->reponse instanceof \Throwable) {
            throw $this->reponse;
        }

        return new ReponseIA($this->reponse, 1200, 80, $this->fournisseur, 'modele-de-test');
    }

    public static function indisponible(): self
    {
        return new self(new IAIndisponibleException("L'assistant est momentanément indisponible."));
    }
}
