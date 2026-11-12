<?php

namespace App\IA;

use App\Exceptions\IAIndisponibleException;
use Illuminate\Support\Facades\Log;

/**
 * Appelle Gemini ; s'il échoue (429 quota, 503, clé invalide, réseau…), appelle Groq.
 * Si les deux échouent : IAIndisponibleException (→ 503 côté API).
 * Lié à l'interface ClientIA dans AppServiceProvider.
 */
class ClientIAAvecSecours implements ClientIA
{
    public function __construct(
        private GeminiClient $principal,
        private GroqClient $secours,
    ) {}

    public function repondre(string $systeme, string $message): ReponseIA
    {
        try {
            return $this->principal->repondre($systeme, $message);
        } catch (ErreurFournisseurIA $e) {
            Log::info('Assistant IA : bascule vers Groq', ['raison' => $e->getMessage(), 'statut' => $e->statut]);
        }

        try {
            return $this->secours->repondre($systeme, $message);
        } catch (ErreurFournisseurIA $e) {
            Log::warning('Assistant IA indisponible : Gemini et Groq ont échoué', ['raison' => $e->getMessage()]);

            throw new IAIndisponibleException("L'assistant est momentanément indisponible.", 0, $e);
        }
    }
}
