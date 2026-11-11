<?php

namespace App\IA;

use RuntimeException;

/**
 * Un fournisseur (Gemini ou Groq) n'a pas répondu. ClientIAAvecSecours essaie alors le suivant.
 */
class ErreurFournisseurIA extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $statut = null, ?\Throwable $precedente = null)
    {
        parent::__construct($message, 0, $precedente);
    }
}
