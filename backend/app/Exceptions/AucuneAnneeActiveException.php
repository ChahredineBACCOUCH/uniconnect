<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Aucune année universitaire active : la génération des groupes est impossible
 * (SCRUM-82). L'API répond 422 avec un message explicite.
 */
class AucuneAnneeActiveException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            "Aucune année universitaire n'est active : activez une année avant de générer les groupes."
        );
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
