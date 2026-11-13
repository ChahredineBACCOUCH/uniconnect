<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnonceRequest;
use App\Http\Resources\AnnonceResource;
use App\Mail\AnnoncePubliee;
use App\Models\Annonce;
use App\Models\Espace;
use App\Notifications\NouvelleAnnonce;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SCRUM-123 : annonces officielles d'un espace, séparées de la discussion.
 *
 * Lecture ouverte aux membres ; publication réservée au propriétaire ou à un
 * co-gestionnaire, avec d'éventuelles pièces jointes (SCRUM-124). Chaque
 * publication prévient les membres (notification applicative) et, si
 * `notifier_email` est coché, leur envoie aussi un e-mail mis en file d'attente.
 */
class AnnonceController extends Controller
{
    public function index(Espace $espace): AnonymousResourceCollection
    {
        $this->authorize('view', $espace);

        $annonces = $espace->annonces()
            ->with(['auteur', 'piecesJointes'])
            ->orderByDesc('id')
            ->get();

        return AnnonceResource::collection($annonces);
    }

    public function store(StoreAnnonceRequest $request, Espace $espace): JsonResponse
    {
        $this->authorize('update', $espace);

        $annonce = $espace->annonces()->create([
            'auteur_id' => $request->user()->id,
            'titre' => (string) $request->validated('titre'),
            'contenu' => (string) $request->validated('contenu'),
            'notifier_email' => $request->boolean('notifier_email'),
        ]);

        foreach ($request->file('fichiers', []) as $fichier) {
            $chemin = Storage::putFileAs(
                'espaces/'.$espace->id.'/annonces',
                $fichier,
                Str::uuid().'.'.$fichier->extension(),
            );

            $annonce->piecesJointes()->create([
                'nom' => $fichier->getClientOriginalName(),
                'chemin' => (string) $chemin,
                'mime' => (string) $fichier->getMimeType(),
                'taille_octets' => $fichier->getSize(),
            ]);
        }

        $membres = $espace->membres()->get();
        $annonce->loadMissing(['espace', 'auteur']);

        Notification::send($membres, new NouvelleAnnonce($annonce));

        if ($annonce->notifier_email) {
            foreach ($membres as $membre) {
                Mail::to($membre->email)->queue(new AnnoncePubliee($annonce, $membre));
            }
        }

        return (new AnnonceResource($annonce->load(['auteur', 'piecesJointes'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Supprime une annonce. Seul son auteur y est autorisé.
     */
    public function destroy(Request $request, Annonce $annonce): Response
    {
        if ($annonce->auteur_id !== $request->user()->id) {
            abort(403, "Seul l'auteur peut supprimer cette annonce.");
        }

        $annonce->delete();

        return response()->noContent();
    }
}
