<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompteAutoriseResource;
use App\Models\CompteAutorise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SCRUM-75 : liste des personnes autorisées à créer un compte.
 *
 * L'administration peut consulter (avec filtres et recherche), créer, modifier,
 * désactiver ou supprimer une autorisation. Une autorisation rattachée à un
 * compte utilisateur ne peut pas être supprimée : elle peut seulement être
 * désactivée.
 */
class CompteAutoriseController extends Controller
{
    /** Taille de page par défaut de la liste (docs/api.md). */
    private const PAR_PAGE = 20;

    /**
     * Messages en français : l'application n'a pas de fichiers de traduction,
     * Laravel afficherait sinon « The matricule field is required. ».
     *
     * @var array<string, string>
     */
    private const MESSAGES = [
        'matricule.required' => 'Le matricule est obligatoire.',
        'matricule.string' => 'Le matricule est invalide.',
        'matricule.max' => 'Le matricule ne peut pas dépasser 30 caractères.',
        'matricule.unique' => 'Ce matricule figure déjà dans la liste des comptes autorisés.',
        'nom.required' => 'Le nom est obligatoire.',
        'nom.max' => 'Le nom ne peut pas dépasser 80 caractères.',
        'prenom.required' => 'Le prénom est obligatoire.',
        'prenom.max' => 'Le prénom ne peut pas dépasser 80 caractères.',
        'email.required' => "L'adresse e-mail est obligatoire.",
        'email.email' => "L'adresse e-mail est invalide.",
        'email.max' => "L'adresse e-mail ne peut pas dépasser 190 caractères.",
        'email.unique' => 'Cette adresse e-mail figure déjà dans la liste des comptes autorisés.',
        'profil.required' => 'Le profil est obligatoire.',
        'profil.enum' => 'Le profil doit être ETUDIANT, ENSEIGNANT ou ADMINISTRATION.',
        'classe_id.integer' => 'La classe est invalide.',
        'classe_id.exists' => "La classe choisie n'existe pas.",
        'classe_id.required' => 'La classe est obligatoire pour un étudiant.',
        'etat.enum' => "L'état du compte est invalide.",
        'par_page.integer' => 'Le nombre de lignes par page est invalide.',
        'par_page.min' => 'Le nombre de lignes par page doit être au moins 1.',
        'par_page.max' => 'Le nombre de lignes par page ne peut pas dépasser 100.',
        'page.integer' => 'Le numéro de page est invalide.',
        'page.min' => 'Le numéro de page doit être au moins 1.',
    ];

    /**
     * Liste paginée des comptes autorisés.
     *
     * Filtres : `profil`, `classe_id`, `etat`. Recherche `q` sur le nom et le
     * matricule (insensible à la casse : ILIKE sur PostgreSQL).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $donnees = $request->validate([
            'profil' => ['sometimes', Rule::enum(Profil::class)],
            'classe_id' => ['sometimes', 'integer', Rule::exists('classes', 'id')],
            'etat' => ['sometimes', Rule::enum(EtatCompte::class)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], self::MESSAGES);

        $requete = CompteAutorise::query()->with('classe');

        foreach (['profil', 'classe_id', 'etat'] as $filtre) {
            if (isset($donnees[$filtre])) {
                $requete->where($filtre, $donnees[$filtre]);
            }
        }

        $recherche = trim((string) ($donnees['q'] ?? ''));

        if ($recherche !== '') {
            // `whereLike` compile en ILIKE sur PostgreSQL et en LIKE (déjà
            // insensible à la casse pour l'ASCII) sur SQLite.
            $requete->where(function ($sousRequete) use ($recherche): void {
                $sousRequete
                    ->whereLike('nom', "%{$recherche}%")
                    ->orWhereLike('matricule', "%{$recherche}%");
            });
        }

        $comptes = $requete
            ->orderBy('nom')
            ->orderBy('prenom')
            ->orderBy('id')
            ->paginate($donnees['par_page'] ?? self::PAR_PAGE);

        // Format des listes paginées de docs/api.md : { data, meta: { page, par_page, total } }.
        // On passe la collection (et non le paginator) pour maîtriser entièrement
        // le bloc `meta`, sans les clés `links`/`current_page` de Laravel.
        return CompteAutoriseResource::collection($comptes->getCollection())->additional([
            'meta' => [
                'page' => $comptes->currentPage(),
                'par_page' => $comptes->perPage(),
                'total' => $comptes->total(),
            ],
        ]);
    }

    /**
     * Ajoute une personne à la liste des comptes autorisés.
     */
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'matricule' => [
                'required',
                'string',
                'max:30',
                Rule::unique('comptes_autorises', 'matricule'),
            ],
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'email' => [
                'required',
                'string',
                'email',
                'max:190',
                Rule::unique('comptes_autorises', 'email'),
            ],
            'profil' => ['required', Rule::enum(Profil::class)],
            'classe_id' => [
                'nullable',
                'integer',
                Rule::exists('classes', 'id'),
                Rule::requiredIf(fn (): bool => $request->input('profil') === Profil::ETUDIANT->value),
            ],
            'etat' => ['sometimes', Rule::enum(EtatCompte::class)],
        ], self::MESSAGES);

        $compte = CompteAutorise::create([
            ...$donnees,
            'etat' => $donnees['etat'] ?? EtatCompte::NON_CREE,
        ]);

        return (new CompteAutoriseResource($compte->load('classe')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Modifie une autorisation existante.
     */
    public function update(Request $request, CompteAutorise $compteAutorise): CompteAutoriseResource
    {
        $donnees = $request->validate([
            'matricule' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('comptes_autorises', 'matricule')->ignore($compteAutorise->getKey()),
            ],
            'nom' => ['sometimes', 'required', 'string', 'max:80'],
            'prenom' => ['sometimes', 'required', 'string', 'max:80'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:190',
                Rule::unique('comptes_autorises', 'email')->ignore($compteAutorise->getKey()),
            ],
            'profil' => ['sometimes', 'required', Rule::enum(Profil::class)],
            'classe_id' => ['sometimes', 'nullable', 'integer', Rule::exists('classes', 'id')],
            'etat' => ['sometimes', 'required', Rule::enum(EtatCompte::class)],
        ], self::MESSAGES);

        // La validation porte sur les valeurs finales : passer un étudiant à un
        // autre profil peut libérer la classe, mais l'inverse l'exige.
        $profil = Profil::from($donnees['profil'] ?? $compteAutorise->profil->value);
        $classeId = array_key_exists('classe_id', $donnees)
            ? $donnees['classe_id']
            : $compteAutorise->classe_id;

        if ($profil === Profil::ETUDIANT && $classeId === null) {
            throw ValidationException::withMessages([
                'classe_id' => self::MESSAGES['classe_id.required'],
            ]);
        }

        $compteAutorise->update($donnees);

        return new CompteAutoriseResource($compteAutorise->refresh()->load('classe'));
    }

    /**
     * Désactive une autorisation sans la supprimer (l'historique est conservé).
     */
    public function desactiver(CompteAutorise $compteAutorise): CompteAutoriseResource
    {
        $compteAutorise->update(['etat' => EtatCompte::DESACTIVE]);

        return new CompteAutoriseResource($compteAutorise->refresh()->load('classe'));
    }

    /**
     * Supprime une autorisation qui n'a pas encore servi à créer un compte.
     */
    public function destroy(CompteAutorise $compteAutorise): JsonResponse
    {
        if ($compteAutorise->user_id !== null) {
            return response()->json([
                'message' => "Ce compte a déjà été créé : l'autorisation ne peut pas être supprimée. Désactivez-la à la place.",
            ], 409);
        }

        $compteAutorise->delete();

        return response()->json(null, 204);
    }
}
