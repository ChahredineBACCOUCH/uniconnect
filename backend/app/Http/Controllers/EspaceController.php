<?php

namespace App\Http\Controllers;

use App\Http\Resources\EspaceResource;
use App\Models\Espace;
use Illuminate\Http\Request;

/**
 * SCRUM-95 : informations affichées dans l'en-tête de l'écran d'un espace.
 */
class EspaceController extends Controller
{
    /**
     * Détail d'un espace accessible uniquement à ses membres.
     */
    public function show(Request $request, Espace $espace): EspaceResource
    {
        $this->authorize('view', $espace);

        $adhesion = $espace->adhesions()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Attributs calculés pour l'utilisateur courant, lus par EspaceResource.
        $espace->role = $adhesion->role->value;
        $espace->nb_membres = $espace->adhesions()->count();

        return new EspaceResource($espace);
    }
}
