<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GenerationGroupesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SCRUM-82 : groupes organisationnels de l'année active.
 *
 * GET  /api/admin/groupes/apercu  → liste les groupes à créer, sans écrire
 * POST /api/admin/groupes/generer → crée les groupes et leurs membres (idempotent)
 */
class GroupeController extends Controller
{
    public function apercu(GenerationGroupesService $service): JsonResponse
    {
        return response()->json($service->apercu());
    }

    public function generer(Request $request, GenerationGroupesService $service): JsonResponse
    {
        // Compatibilité avec le contrat docs/api.md : POST …?apercu=1.
        if ($request->boolean('apercu')) {
            return response()->json($service->apercu());
        }

        return response()->json($service->generer($request->user()));
    }
}
