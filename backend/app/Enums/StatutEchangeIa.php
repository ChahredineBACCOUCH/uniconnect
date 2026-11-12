<?php

namespace App\Enums;

/**
 * Résultat d'une question posée à l'Assistant IA (colonne echanges_ia.statut).
 */
enum StatutEchangeIa: string
{
    // Réponse fondée sur les ressources du cours, avec sources citées.
    case OK = 'OK';
    // Rien de pertinent dans le cours : l'IA n'est pas appelée, ou elle répond JE_NE_SAIS_PAS.
    case SANS_SOURCE = 'SANS_SOURCE';
    // Gemini et Groq n'ont pas répondu.
    case ERREUR = 'ERREUR';
}
