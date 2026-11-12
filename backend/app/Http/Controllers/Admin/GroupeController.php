<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EtatCompte;
use App\Enums\EtatEspace;
use App\Enums\Profil;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Http\Controllers\Controller;
use App\Http\Resources\GroupeResource;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Espace;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use App\Services\GenerationGroupesService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SCRUM-82 / SCRUM-83 : groupes organisationnels de l'année active.
 *
 * GET  /api/admin/groupes                    → liste filtrable (écran A06)
 * GET  /api/admin/groupes/filtres            → options des filtres
 * GET  /api/admin/groupes/apercu             → groupes à créer, sans écrire
 * POST /api/admin/groupes/generer            → crée les groupes et leurs membres (idempotent)
 * POST /api/admin/groupes/{espace}/synchroniser → réaligne les membres d'un groupe
 * POST /api/admin/groupes/{espace}/archiver  → archive un groupe
 */
class GroupeController extends Controller
{
    /**
     * Messages en français : l'application n'a pas de fichiers de traduction.
     *
     * @var array<string, string>
     */
    private const MESSAGES = [
        'filiere_id.integer' => 'La filière est invalide.',
        'filiere_id.exists' => "La filière choisie n'existe pas.",
        'niveau_id.integer' => 'Le niveau est invalide.',
        'niveau_id.exists' => "Le niveau choisi n'existe pas.",
        'classe_id.integer' => 'La classe est invalide.',
        'classe_id.exists' => "La classe choisie n'existe pas.",
        'enseignant_id.integer' => "L'enseignant est invalide.",
        'enseignant_id.exists' => "L'enseignant choisi n'existe pas.",
    ];

    /**
     * Liste des groupes organisationnels de l'année active, avec le nombre de
     * membres et le propriétaire.
     */
    public function index(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'filiere_id' => ['sometimes', 'integer', Rule::exists('filieres', 'id')],
            'niveau_id' => ['sometimes', 'integer', Rule::exists('niveaux', 'id')],
            'classe_id' => ['sometimes', 'integer', Rule::exists('classes', 'id')],
            'enseignant_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
        ], self::MESSAGES);

        $annee = AnneeUniversitaire::active()->first();

        // Sans année active, il n'y a aucun groupe : l'écran affiche alors une
        // alerte et la génération répondra 422 avec le message explicite.
        if ($annee === null) {
            return response()->json(['annee' => null, 'data' => [], 'total' => 0]);
        }

        $requete = Espace::query()
            ->where('type', TypeEspace::ORGANISATIONNEL->value)
            ->where('annee_id', $annee->id)
            ->with([
                'classe.filiere',
                'classe.niveau',
                'matiere.classes' => fn ($query) => $query->where('classes.annee_id', $annee->id),
                'adhesions' => fn ($query) => $query
                    ->where('role', RoleMembre::PROPRIETAIRE->value)
                    ->with('user'),
            ])
            ->withCount('adhesions as nombre_membres');

        $this->appliquerFiltres($requete, $donnees);

        $groupes = $requete
            // Groupes de classe d'abord, puis groupes de matière, par nom.
            ->orderByRaw('CASE WHEN classe_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('nom')
            ->get();

        return response()->json([
            'annee' => ['id' => $annee->id, 'libelle' => $annee->libelle],
            'data' => GroupeResource::collection($groupes)->resolve($request),
            'total' => $groupes->count(),
        ]);
    }

    /**
     * Options des filtres (filières, niveaux, classes et enseignants).
     */
    public function filtres(): JsonResponse
    {
        $annee = AnneeUniversitaire::active()->first();

        if ($annee === null) {
            return response()->json([
                'annee' => null,
                'filieres' => [],
                'niveaux' => [],
                'classes' => [],
                'enseignants' => [],
            ]);
        }

        return response()->json([
            'annee' => ['id' => $annee->id, 'libelle' => $annee->libelle],
            'filieres' => Filiere::query()
                ->where('actif', true)
                ->orderBy('libelle')
                ->get(['id', 'code', 'libelle']),
            'niveaux' => Niveau::query()
                ->where('actif', true)
                ->orderBy('code')
                ->get(['id', 'code', 'libelle']),
            'classes' => Classe::query()
                ->where('annee_id', $annee->id)
                ->where('actif', true)
                ->orderBy('code')
                ->get(['id', 'code', 'libelle', 'filiere_id', 'niveau_id']),
            'enseignants' => User::query()
                ->where('profil', Profil::ENSEIGNANT->value)
                ->where('etat', EtatCompte::ACTIF->value)
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get(['id', 'nom', 'prenom'])
                ->map(fn (User $enseignant): array => [
                    'id' => $enseignant->id,
                    'nom' => $enseignant->nom,
                    'prenom' => $enseignant->prenom,
                    'nom_complet' => trim($enseignant->prenom.' '.$enseignant->nom),
                ])
                ->values(),
        ]);
    }

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

    /**
     * Relance le rattachement des membres pour un seul groupe.
     */
    public function synchroniser(Espace $espace, GenerationGroupesService $service): JsonResponse
    {
        $this->verifierGroupeOrganisationnel($espace);

        if ($espace->etat !== EtatEspace::ACTIF) {
            return response()->json([
                'message' => 'Un groupe archivé ne peut pas être synchronisé.',
            ], 409);
        }

        $resultat = $service->synchroniser($espace);

        return response()->json([
            'groupe' => $this->ressource($espace->refresh()),
            'membres_ajoutes' => $resultat['membres_ajoutes'],
        ]);
    }

    /**
     * Archive un groupe : il reste consultable mais ne reçoit plus de membres.
     */
    public function archiver(Espace $espace): JsonResponse
    {
        $this->verifierGroupeOrganisationnel($espace);

        $espace->update(['etat' => EtatEspace::ARCHIVE->value]);

        return response()->json(['groupe' => $this->ressource($espace->refresh())]);
    }

    /**
     * @param  Builder<Espace>  $requete
     * @param  array<string, mixed>  $donnees
     */
    private function appliquerFiltres(Builder $requete, array $donnees): void
    {
        // Un groupe de matière couvre toutes les classes de l'année qui suivent
        // cette matière : les filtres portent donc aussi sur ces classes.
        if (isset($donnees['classe_id'])) {
            $classeId = (int) $donnees['classe_id'];

            $requete->where(function (Builder $sousRequete) use ($classeId): void {
                $sousRequete
                    ->where('classe_id', $classeId)
                    ->orWhereHas('matiere.classes', fn (Builder $query) => $query->where('classes.id', $classeId));
            });
        }

        if (isset($donnees['filiere_id'])) {
            $filiereId = (int) $donnees['filiere_id'];

            $requete->where(function (Builder $sousRequete) use ($filiereId): void {
                $sousRequete
                    ->whereHas('classe', fn (Builder $query) => $query->where('filiere_id', $filiereId))
                    ->orWhereHas('matiere.classes', fn (Builder $query) => $query->where('filiere_id', $filiereId));
            });
        }

        if (isset($donnees['niveau_id'])) {
            $niveauId = (int) $donnees['niveau_id'];

            $requete->where(function (Builder $sousRequete) use ($niveauId): void {
                $sousRequete
                    ->whereHas('classe', fn (Builder $query) => $query->where('niveau_id', $niveauId))
                    ->orWhereHas('matiere.classes', fn (Builder $query) => $query->where('niveau_id', $niveauId));
            });
        }

        if (isset($donnees['enseignant_id'])) {
            $enseignantId = (int) $donnees['enseignant_id'];

            $requete->whereHas('adhesions', fn (Builder $query) => $query
                ->where('role', RoleMembre::PROPRIETAIRE->value)
                ->where('user_id', $enseignantId));
        }
    }

    private function ressource(Espace $espace): GroupeResource
    {
        $espace
            ->load([
                'classe.filiere',
                'classe.niveau',
                'matiere.classes' => fn ($query) => $query->where('classes.annee_id', $espace->annee_id),
                'adhesions' => fn ($query) => $query
                    ->where('role', RoleMembre::PROPRIETAIRE->value)
                    ->with('user'),
            ])
            ->loadCount('adhesions as nombre_membres');

        return new GroupeResource($espace);
    }

    private function verifierGroupeOrganisationnel(Espace $espace): void
    {
        abort_unless($espace->type === TypeEspace::ORGANISATIONNEL, 404);
    }
}
