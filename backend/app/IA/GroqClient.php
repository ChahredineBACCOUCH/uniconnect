<?php

namespace App\IA;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Groq, offre gratuite (fournisseur de secours, API compatible OpenAI). Clé et modèle : config/services.php.
 */
class GroqClient implements ClientIA
{
    public const TIMEOUT = 30;

    public function repondre(string $systeme, string $message): ReponseIA
    {
        $cle = config('services.groq.key');
        $modele = config('services.groq.model');
        if (blank($cle) || blank($modele)) {
            throw new ErreurFournisseurIA('Groq non configuré (GROQ_API_KEY ou GROQ_MODEL vide)');
        }

        try {
            $r = Http::withToken($cle)
                ->acceptJson()
                ->timeout(self::TIMEOUT)
                ->post(config('services.groq.url').'/chat/completions', [
                    'model' => $modele,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systeme],
                        ['role' => 'user', 'content' => $message],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new ErreurFournisseurIA('Groq injoignable (réseau ou délai dépassé)', null, $e);
        }

        if ($r->failed()) {
            $raison = $r->status() === 429 ? 'quota gratuit dépassé' : ($r->json('error.message') ?? 'erreur');
            throw new ErreurFournisseurIA("Groq a répondu HTTP {$r->status()} ({$raison})", $r->status());
        }

        $texte = (string) $r->json('choices.0.message.content', '');
        if (trim($texte) === '') {
            throw new ErreurFournisseurIA('Groq a renvoyé une réponse vide');
        }

        return new ReponseIA(
            texte: trim($texte),
            tokensEntree: (int) $r->json('usage.prompt_tokens', 0),
            tokensSortie: (int) $r->json('usage.completion_tokens', 0),
            fournisseur: 'groq',
            modele: $modele,
        );
    }
}
