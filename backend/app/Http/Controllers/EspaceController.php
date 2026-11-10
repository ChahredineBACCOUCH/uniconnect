<?php

namespace App\Http\Controllers;

use App\Enums\Droit;
use App\Enums\EtatEspace;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Http\Requests\StoreEspaceRequest;
use App\Http\Requests\UpdateEspaceRequest;
use App\Http\Resources\EspaceResource;
use App\Models\Adhesion;
use App\Models\Espace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SCRUM-95 : détail d'un espace (en-tête de l'écran).
 * SCRUM-87 : création d'un groupe privé et modification de ses informations.
 */
class EspaceController extends Controller
{
    /**
     * Détail d'un espace accessible uniquement à ses membres.
     */
    public function show(Request $request, Espace $espace): EspaceResource
    {
        $this->authorize('view', $espace);

        return EspaceResource::pour($espace, $request->user());
    }

    /**
     * POST /api/espaces : crée un groupe privé. Le créateur en devient le
     * propriétaire. Il faut le droit CREER_GROUPE_PRIVE.
     */
    public function store(StoreEspaceRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->possedeDroit(Droit::CREER_GROUPE_PRIVE)) {
            abort(403, "Vous n'avez pas le droit de créer un groupe privé.");
        }

        $donnees = $request->validated();

        $espace = DB::transaction(function () use ($donnees, $user): Espace {
            $espace = Espace::create([
                'type' => TypeEspace::PRIVE,
                'nom' => $donnees['nom'],
                'description' => $donnees['description'] ?? null,
                'etat' => EtatEspace::ACTIF,
                // Clé absente : durée par défaut ; `null` explicite : illimitée.
                'duree_visibilite_heures' => array_key_exists('duree_visibilite_heures', $donnees)
                    ? $donnees['duree_visibilite_heures']
                    : config('uniconnect.duree_defaut_heures.PRIVE'),
                'duree_courte_autorisee' => $donnees['duree_courte_autorisee'] ?? false,
                'mode_annonce' => $donnees['mode_annonce'] ?? false,
                'created_by' => $user->id,
            ]);

            Adhesion::create([
                'espace_id' => $espace->id,
                'user_id' => $user->id,
                'role' => RoleMembre::PROPRIETAIRE,
            ]);

            return $espace;
        });

        return EspaceResource::pour($espace, $user)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/espaces/{id} : nom et description (propriétaire ou co-gestionnaire).
     */
    public function update(UpdateEspaceRequest $request, Espace $espace): EspaceResource
    {
        $this->authorize('update', $espace);

        if ($espace->etat === EtatEspace::ARCHIVE) {
            abort(403, 'Cet espace est archivé : il ne peut plus être modifié.');
        }

        $espace->update($request->validated());

        return EspaceResource::pour($espace, $request->user());
    }
}
