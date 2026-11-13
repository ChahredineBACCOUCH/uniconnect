<?php

namespace App\Http\Controllers;

use App\Enums\EtatEspace;
use App\Enums\RoleMembre;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SCRUM-94 : API de discussion d'un espace.
 *
 * Lecture (membre), envoi (membre, sauf espace archivé ou mode annonce),
 * modification et suppression (auteur uniquement).
 */
class MessageController extends Controller
{
    private const PAR_PAGE = 30;

    /**
     * Liste les messages visibles de l'espace, du plus ancien au plus récent.
     *
     * `?after_id=` ne renvoie que les messages plus récents (polling toutes les 5 s).
     */
    public function index(Request $request, Espace $espace)
    {
        $this->authorize('view', $espace);

        $donnees = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limite = (int) ($donnees['limite'] ?? self::PAR_PAGE);
        $afterId = isset($donnees['after_id']) ? (int) $donnees['after_id'] : null;

        $requete = Message::query()
            ->where('espace_id', $espace->id)
            ->where('masque', false)
            ->where(function ($q) {
                $q->whereNull('expire_le')
                    ->orWhere('expire_le', '>', now());
            })
            // Pièces jointes (SCRUM-136) : chargées avec la discussion.
            ->with(['auteur', 'piecesJointes']);

        if ($afterId !== null) {
            // Polling : uniquement les nouveaux messages, déjà dans l'ordre d'affichage.
            $requete->where('id', '>', $afterId)->orderBy('id');
        } else {
            // Première ouverture : les 30 derniers messages, remis dans l'ordre chronologique.
            $requete->orderByDesc('id');
        }

        $page = $requete->cursorPaginate($limite);

        if ($afterId === null) {
            $page->setCollection($page->getCollection()->reverse()->values());
        }

        $this->marquerCommeLu($request->user()->id, $espace, $page->getCollection());

        return MessageResource::collection($page);
    }

    /**
     * Publie un message. `expire_le` est figé à l'envoi (non rétroactif).
     */
    public function store(StoreMessageRequest $request, Espace $espace)
    {
        $this->authorize('view', $espace);

        if ($espace->etat === EtatEspace::ARCHIVE) {
            abort(403, "Cet espace est archivé : impossible d'envoyer un message.");
        }

        $adhesion = $espace->adhesions()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($espace->mode_annonce && $adhesion->role === RoleMembre::MEMBRE) {
            abort(403, 'Cet espace est en mode annonce : seuls les gestionnaires peuvent publier.');
        }

        $message = Message::create([
            'espace_id' => $espace->id,
            'auteur_id' => $request->user()->id,
            'parent_id' => $request->validated('parent_id'),
            'contenu' => (string) $request->validated('contenu'),
            'expire_le' => $this->calculerExpiration(
                $espace,
                $request->validated('duree_visibilite_heures'),
            ),
        ]);

        foreach ($request->file('fichiers', []) as $fichier) {
            $chemin = Storage::putFileAs(
                'espaces/'.$espace->id,
                $fichier,
                Str::uuid().'.'.$fichier->extension(),
            );

            $message->piecesJointes()->create([
                'nom' => $fichier->getClientOriginalName(),
                'chemin' => (string) $chemin,
                'mime' => (string) $fichier->getMimeType(),
                'taille_octets' => $fichier->getSize(),
                // La pièce jointe expire en même temps que le message.
                'expire_le' => $message->expire_le,
            ]);
        }

        return (new MessageResource($message->load(['auteur', 'piecesJointes'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Modifie le contenu d'un message. Seul l'auteur y est autorisé.
     */
    public function update(UpdateMessageRequest $request, Message $message)
    {
        if ($request->user()->cannot('update', $message)) {
            abort(403, "Seul l'auteur peut modifier ce message.");
        }

        $message->update([
            'contenu' => (string) $request->validated('contenu'),
            'modifie_le' => now(),
        ]);

        return new MessageResource($message->load(['auteur', 'piecesJointes']));
    }

    /**
     * Supprime un message. Seul l'auteur y est autorisé.
     */
    public function destroy(Request $request, Message $message): Response
    {
        if ($request->user()->cannot('delete', $message)) {
            abort(403, "Seul l'auteur peut supprimer ce message.");
        }

        $message->delete();

        return response()->noContent();
    }

    /**
     * Durée de visibilité : celle de l'espace, ou la durée plus courte choisie.
     * `null` signifie une durée illimitée.
     */
    private function calculerExpiration(Espace $espace, ?int $dureeChoisie): ?Carbon
    {
        $duree = $dureeChoisie ?? $espace->duree_visibilite_heures;

        if ($duree === null) {
            return null;
        }

        return now()->addHours($duree);
    }

    /**
     * Enregistre le dernier message lu. On ne recule jamais : le max courant
     * n'est remplacé que par un identifiant plus grand.
     *
     * @param  Collection<int, Message>  $messages
     */
    private function marquerCommeLu(int $userId, Espace $espace, Collection $messages): void
    {
        $dernierId = $messages->max('id');

        if ($dernierId === null) {
            return;
        }

        Adhesion::query()
            ->where('espace_id', $espace->id)
            ->where('user_id', $userId)
            ->where(function ($q) use ($dernierId) {
                $q->whereNull('dernier_message_lu_id')
                    ->orWhere('dernier_message_lu_id', '<', $dernierId);
            })
            ->update(['dernier_message_lu_id' => $dernierId]);
    }
}
