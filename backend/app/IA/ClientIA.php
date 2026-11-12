<?php

namespace App\IA;

/**
 * Un modèle d'IA qui répond à un prompt (SCRUM-100).
 * Implémentations : GeminiClient, GroqClient et ClientIAAvecSecours (Gemini puis Groq).
 * Dans les tests, remplacer par un faux client : tests/Fakes/FakeClientIA.php.
 */
interface ClientIA
{
    /**
     * @throws ErreurFournisseurIA si ce fournisseur ne répond pas (quota, clé, réseau…)
     */
    public function repondre(string $systeme, string $message): ReponseIA;
}
