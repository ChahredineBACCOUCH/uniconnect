<?php

namespace App\Http\Controllers;

use App\Enums\EtatEspace;
use App\Http\Requests\StoreRessourceRequest;
use App\Http\Requests\UpdateRessourceRequest;
use App\Http\Resources\ChapitreResource;
use App\Http\Resources\RessourceResource;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\Ressource;
use App\Models\User;
use App\Notifications\NouvelleRessource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SCRUM-126 : ressources pédagogiques d'un espace.
 *
 * Les fichiers sont rangés dans `espaces/{id}/ressources` sur le disque par
 * défaut (S3 en production, local en développement). L'observer de SCRUM-98
 * lance l'indexation IA dès qu'un PDF est ajouté ou remplacé.
 */
class RessourceController extends Controller
{
    /**
     * GET /api/espaces/{espace}/ressources : chapitres et ressources groupées.
     */
    public function index(Espace $espace): AnonymousResourceCollection
    {
        $this->authorize('view', $espace);

        return ChapitreResource::collection($this->chapitresAvecRessources($espace));
    }

    /**
     * POST /api/espaces/{espace}/ressources : le chapitre est dans le corps.
     */
    public function store(StoreRessourceRequest $request, Espace $espace): JsonResponse
    {
        $donnees = $request->validated();

        if (! isset($donnees['chapitre_id'])) {
            throw ValidationException::withMessages([
                'chapitre_id' => 'Le chapitre est obligatoire.',
            ]);
        }

        $chapitre = $espace->chapitres()->whereKey($donnees['chapitre_id'])->first();

        if ($chapitre === null) {
            throw ValidationException::withMessages([
                'chapitre_id' => "Le chapitre n'existe pas dans cet espace.",
            ]);
        }

        return $this->creer($request, $espace, $chapitre);
    }

    /**
     * POST /api/chapitres/{chapitre}/ressources (route documentée dans api.md).
     */
    public function storeDansChapitre(StoreRessourceRequest $request, Chapitre $chapitre): JsonResponse
    {
        return $this->creer($request, $chapitre->espace, $chapitre);
    }

    public function update(UpdateRessourceRequest $request, Ressource $ressource): RessourceResource
    {
        $this->authorize('update', $ressource);
        $this->refuserSiArchive($ressource->chapitre->espace);

        $donnees = $request->validated();
        $ancienChemin = $ressource->chemin_fichier;

        $attributs = [];

        if (array_key_exists('titre', $donnees)) {
            $attributs['titre'] = $donnees['titre'];
        }

        if (array_key_exists('description', $donnees)) {
            $attributs['description'] = $donnees['description'];
        }

        if ($request->hasFile('fichier')) {
            $fichier = $request->file('fichier');

            $attributs['chemin_fichier'] = Storage::putFile(
                'espaces/'.$ressource->chapitre->espace_id.'/ressources',
                $fichier,
            );
            $attributs['mime'] = $fichier->getMimeType();
            $attributs['taille'] = $fichier->getSize();
            $attributs['url'] = null;
        } elseif ($request->filled('url')) {
            $attributs['chemin_fichier'] = null;
            $attributs['mime'] = null;
            $attributs['taille'] = null;
            $attributs['url'] = $donnees['url'];
        }

        // Le contenu change : les fragments de l'ancien fichier ne valent plus
        // rien. L'observer relance l'indexation si le nouveau fichier est un PDF.
        if ($request->hasFile('fichier') || $request->filled('url')) {
            $ressource->fragments()->delete();
            $attributs['indexee_ia'] = false;
        }

        $ressource->update($attributs);

        if ($ancienChemin !== null && $ancienChemin !== $ressource->chemin_fichier) {
            Storage::delete($ancienChemin);
        }

        return new RessourceResource($ressource->fresh());
    }

    public function destroy(Ressource $ressource): Response
    {
        $this->authorize('delete', $ressource);
        $this->refuserSiArchive($ressource->chapitre->espace);

        $chemin = $ressource->chemin_fichier;
        $ressource->delete();

        if ($chemin !== null) {
            Storage::delete($chemin);
        }

        return response()->noContent();
    }

    /**
     * PATCH /api/ressources/{ressource}/deplacer { chapitre_id }.
     */
    public function deplacer(Request $request, Ressource $ressource): RessourceResource
    {
        $this->authorize('update', $ressource);

        $espace = $ressource->chapitre->espace;
        $this->refuserSiArchive($espace);

        $donnees = $request->validate([
            'chapitre_id' => [
                'required',
                'integer',
                Rule::exists('chapitres', 'id')->where('espace_id', $espace->id),
            ],
        ]);

        $ressource->update(['chapitre_id' => $donnees['chapitre_id']]);

        return new RessourceResource($ressource->fresh());
    }

    /**
     * GET /api/ressources/{ressource}/telecharger : lien temporaire (S3) ou
     * envoi direct du fichier (disque local).
     */
    public function telecharger(Ressource $ressource)
    {
        $this->authorize('view', $ressource);

        if ($ressource->chemin_fichier === null) {
            abort(404, 'Cette ressource est un lien : aucun fichier à télécharger.');
        }

        $disque = Storage::disk();

        if (! $disque->exists($ressource->chemin_fichier)) {
            abort(404, 'Le fichier de cette ressource est introuvable.');
        }

        $pilote = config('filesystems.disks.'.config('filesystems.default').'.driver');

        if ($pilote === 's3') {
            return redirect()->away(
                $disque->temporaryUrl($ressource->chemin_fichier, now()->addMinutes(5)),
            );
        }

        return $disque->download(
            $ressource->chemin_fichier,
            basename($ressource->chemin_fichier),
            // Sans cet en-tête, le type est deviné d'après le contenu : un PDF
            // stocké sur le disque local serait annoncé en text/plain.
            $ressource->mime === null ? [] : ['Content-Type' => $ressource->mime],
        );
    }

    private function creer(StoreRessourceRequest $request, Espace $espace, Chapitre $chapitre): JsonResponse
    {
        $this->authorize('create', [Ressource::class, $chapitre]);
        $this->refuserSiArchive($espace);

        $donnees = $request->validated();
        $fichier = $request->file('fichier');

        $ressource = Ressource::create([
            'chapitre_id' => $chapitre->id,
            'auteur_id' => $request->user()->id,
            'titre' => $donnees['titre'],
            'description' => $donnees['description'] ?? null,
            'chemin_fichier' => $fichier
                ? Storage::putFile('espaces/'.$espace->id.'/ressources', $fichier)
                : null,
            'url' => $fichier ? null : $donnees['url'],
            'mime' => $fichier?->getMimeType(),
            'taille' => $fichier?->getSize(),
        ]);

        // SCRUM-121 : les autres membres de l'espace sont prévenus.
        $this->prevenirLesMembres($espace, $ressource, $request->user());

        return (new RessourceResource($ressource))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * SCRUM-121 : chaque membre de l'espace (sauf l'auteur) reçoit une
     * notification, sauf s'il a désactivé les notifications pour cet espace.
     */
    private function prevenirLesMembres(Espace $espace, Ressource $ressource, User $auteur): void
    {
        $membres = $espace->membres()
            ->where('users.id', '!=', $auteur->id)
            ->where('adhesions.notifications_actives', true)
            ->get();

        foreach ($membres as $membre) {
            $membre->notify(new NouvelleRessource($espace, $ressource, $auteur));
        }
    }

    private function chapitresAvecRessources(Espace $espace): Collection
    {
        return $espace->chapitres()
            ->with(['ressources' => fn ($query) => $query->orderBy('id')])
            ->get();
    }

    private function refuserSiArchive(Espace $espace): void
    {
        if ($espace->etat === EtatEspace::ARCHIVE) {
            abort(403, 'Cet espace est archivé : les ressources ne peuvent plus être modifiées.');
        }
    }
}
