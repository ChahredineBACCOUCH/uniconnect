<?php

namespace App\Http\Controllers;

use App\Models\PieceJointe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SCRUM-137 : téléchargement protégé des pièces jointes.
 *
 * Le fichier est toujours servi par l'API, jamais par une URL publique du
 * bucket : le contrôle d'accès est refait à chaque requête par FichierPolicy.
 */
class FichierController extends Controller
{
    public function telecharger(Request $request, PieceJointe $pj): StreamedResponse
    {
        $this->authorize('view', $pj);

        $disque = Storage::disk();

        if (! $disque->exists($pj->chemin)) {
            abort(404, 'Le fichier est introuvable.');
        }

        $entetes = [
            // Le type stocké à l'envoi évite que le navigateur devine le contenu.
            'Content-Type' => $pj->mime,
            'X-Content-Type-Options' => 'nosniff',
        ];

        // Aperçu : les images s'affichent dans le navigateur (Content-Disposition
        // inline). Les autres formats restent des téléchargements.
        if ($request->boolean('apercu') && str_starts_with($pj->mime, 'image/')) {
            return $disque->response($pj->chemin, $pj->nom, $entetes);
        }

        return $disque->download($pj->chemin, $pj->nom, $entetes);
    }
}
