<?php

namespace App\IA;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini, offre gratuite (fournisseur principal). Clé et modèle : config/services.php.
 */
class GeminiClient implements ClientIA
{
    public const TIMEOUT = 30;

    public function repondre(string $systeme, string $message): ReponseIA
    {
        $cle = config('services.gemini.key');
        $modele = config('services.gemini.model');
        if (blank($cle) || blank($modele)) {
            throw new ErreurFournisseurIA('Gemini non configuré (GEMINI_API_KEY ou GEMINI_MODEL vide)');
        }

        try {
            $r = Http::withHeaders(['x-goog-api-key' => $cle])
                ->acceptJson()
                ->timeout(self::TIMEOUT)
                ->post(config('services.gemini.url')."/models/{$modele}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $systeme]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $message]]]],
                    'generationConfig' => ['temperature' => 0.2],
                ]);
        } catch (ConnectionException $e) {
            throw new ErreurFournisseurIA('Gemini injoignable (réseau ou délai dépassé)', null, $e);
        }

        if ($r->failed()) {
            $raison = $r->status() === 429 ? 'quota gratuit dépassé' : ($r->json('error.message') ?? 'erreur');
            throw new ErreurFournisseurIA("Gemini a répondu HTTP {$r->status()} ({$raison})", $r->status());
        }

        $texte = collect($r->json('candidates.0.content.parts', []))->pluck('text')->implode('');
        if (trim($texte) === '') {
            throw new ErreurFournisseurIA('Gemini a renvoyé une réponse vide (finishReason : '.$r->json('candidates.0.finishReason', 'inconnue').')');
        }

        return new ReponseIA(
            texte: trim($texte),
            tokensEntree: (int) $r->json('usageMetadata.promptTokenCount', 0),
            // Les modèles « thinking » comptent aussi leurs tokens de réflexion dans le quota.
            tokensSortie: (int) $r->json('usageMetadata.candidatesTokenCount', 0) + (int) $r->json('usageMetadata.thoughtsTokenCount', 0),
            fournisseur: 'gemini',
            modele: $modele,
        );
    }
}
