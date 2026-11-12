<?php

namespace App\IA;

/**
 * Réponse d'un modèle d'IA, avec ce qu'il faut pour suivre le quota gratuit.
 */
final readonly class ReponseIA
{
    public function __construct(
        public string $texte,
        public int $tokensEntree,
        public int $tokensSortie,
        // gemini | groq : l'IA qui a réellement répondu (enregistré dans echanges_ia.fournisseur).
        public string $fournisseur,
        public ?string $modele = null,
    ) {}
}
