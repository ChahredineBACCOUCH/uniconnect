<?php

namespace App\Http\Controllers;

use App\Enums\EtatEspace;
use App\Http\Resources\ChapitreResource;
use App\Models\Chapitre;
use App\Models\Espace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * SCRUM-126 : chapitres d'un espace et leur ordre d'affichage.
 *
 * Lecture ouverte aux membres ; création, renommage, réordonnancement et
 * suppression réservés au propriétaire ou à un co-gestionnaire.
 */
class ChapitreController extends Controller
{
    public function index(Espace $espace): AnonymousResourceCollection
    {
        $this->authorize('view', $espace);

        return ChapitreResource::collection($this->chapitresAvecRessources($espace));
    }

    public function store(Request $request, Espace $espace): JsonResponse
    {
        $this->authorize('update', $espace);
        $this->refuserSiArchive($espace);

        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:160'],
            'ordre' => ['nullable', 'integer', 'min:0'],
        ]);

        $chapitre = $espace->chapitres()->create([
            'titre' => $donnees['titre'],
            // Sans ordre explicite, le nouveau chapitre passe en dernier.
            'ordre' => $donnees['ordre'] ?? ((int) $espace->chapitres()->max('ordre') + 1),
        ]);

        return (new ChapitreResource($chapitre->load('ressources')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Chapitre $chapitre): ChapitreResource
    {
        $this->authorize('update', $chapitre->espace);
        $this->refuserSiArchive($chapitre->espace);

        $donnees = $request->validate([
            'titre' => ['sometimes', 'required', 'string', 'max:160'],
            'ordre' => ['sometimes', 'integer', 'min:0'],
        ]);

        $chapitre->update($donnees);

        return new ChapitreResource($chapitre->load('ressources'));
    }

    public function destroy(Chapitre $chapitre): Response
    {
        $this->authorize('update', $chapitre->espace);
        $this->refuserSiArchive($chapitre->espace);

        DB::transaction(function () use ($chapitre): void {
            // Les ressources sont supprimées en cascade ; leurs fichiers doivent
            // l'être explicitement pour ne pas rester orphelins sur le disque.
            foreach ($chapitre->ressources as $ressource) {
                if ($ressource->chemin_fichier !== null) {
                    Storage::delete($ressource->chemin_fichier);
                }
            }

            $chapitre->delete();
        });

        return response()->noContent();
    }

    /**
     * POST /api/espaces/{espace}/chapitres/ordre { ids: [3, 1, 2] }.
     */
    public function reordonner(Request $request, Espace $espace): AnonymousResourceCollection
    {
        $this->authorize('update', $espace);
        $this->refuserSiArchive($espace);

        $donnees = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                'distinct',
                Rule::exists('chapitres', 'id')->where('espace_id', $espace->id),
            ],
        ]);

        DB::transaction(function () use ($donnees): void {
            foreach ($donnees['ids'] as $position => $id) {
                Chapitre::query()->whereKey($id)->update(['ordre' => $position]);
            }
        });

        return ChapitreResource::collection($this->chapitresAvecRessources($espace));
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
            abort(403, 'Cet espace est archivé : les chapitres ne peuvent plus être modifiés.');
        }
    }
}
