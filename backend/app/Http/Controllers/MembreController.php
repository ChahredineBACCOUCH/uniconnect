<?php

namespace App\Http\Controllers;

use App\Enums\EtatCompte;
use App\Enums\EtatEspace;
use App\Enums\Profil;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\User;
use App\Notifications\RetraitEspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * SCRUM-90 : membres d'un espace (lister, retirer, changer de rôle, transférer
 * la propriété, quitter).
 *
 * Règle centrale : un espace a **toujours exactement un** propriétaire. Toutes
 * les opérations qui touchent ce rôle passent par une transaction, en retirant
 * l'ancien propriétaire **avant** de nommer le nouveau (index unique partiel
 * `un_proprietaire_par_espace`).
 */
class MembreController extends Controller
{
    /** Ordre d'affichage : propriétaire, co-gestionnaires, puis membres. */
    private const ORDRE_ROLES = [
        RoleMembre::PROPRIETAIRE->value => 0,
        RoleMembre::CO_GESTIONNAIRE->value => 1,
        RoleMembre::MEMBRE->value => 2,
    ];

    /** GET /api/espaces/{id}/membres?q= (membre de l'espace). */
    public function index(Request $request, Espace $espace): JsonResponse
    {
        $this->authorize('view', $espace);

        $q = mb_strtolower(trim((string) $request->query('q', '')));

        $adhesions = $espace->adhesions()
            ->with('user')
            ->when($q !== '', function ($requete) use ($q) {
                $motif = '%'.$q.'%';
                $requete->whereHas('user', fn ($u) => $u
                    ->whereRaw('lower(nom) like ?', [$motif])
                    ->orWhereRaw('lower(prenom) like ?', [$motif])
                    ->orWhereRaw("lower(prenom || ' ' || nom) like ?", [$motif]));
            })
            ->get()
            ->sortBy([
                fn (Adhesion $a, Adhesion $b) => self::ORDRE_ROLES[$a->role->value] <=> self::ORDRE_ROLES[$b->role->value],
                fn (Adhesion $a, Adhesion $b) => strcmp($a->user->nom.' '.$a->user->prenom, $b->user->nom.' '.$b->user->prenom),
            ]);

        return response()->json([
            'data' => $adhesions->map(fn (Adhesion $a) => $this->presenter($a))->values(),
        ]);
    }

    /**
     * PATCH /api/espaces/{id}/membres/{user} `{ role: CO_GESTIONNAIRE | MEMBRE }`
     * (propriétaire uniquement). Pour changer de propriétaire : /transferer.
     */
    public function update(Request $request, Espace $espace, User $user): JsonResponse
    {
        $this->authorize('delete', $espace); // réservé au propriétaire
        $this->verifierNonArchive($espace);

        $role = $request->validate([
            'role' => ['required', Rule::in([RoleMembre::CO_GESTIONNAIRE->value, RoleMembre::MEMBRE->value])],
        ], [
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Le rôle doit être co-gestionnaire ou membre. Pour changer de propriétaire, utilisez « Transférer la propriété ».',
        ])['role'];

        $adhesion = $this->adhesionDe($espace, $user);

        if ($adhesion->role === RoleMembre::PROPRIETAIRE) {
            abort(422, 'Le rôle du propriétaire ne se change pas ici : utilisez « Transférer la propriété ».');
        }

        $adhesion->update(['role' => $role]);

        return response()->json(['data' => $this->presenter($adhesion->load('user'))]);
    }

    /**
     * DELETE /api/espaces/{id}/membres/{user} (propriétaire ou co-gestionnaire).
     * L'accès est coupé immédiatement : EspacePolicy::view ne trouve plus d'adhésion.
     */
    public function destroy(Request $request, Espace $espace, User $user): Response
    {
        $this->authorize('manageMembers', $espace);

        if ($user->id === $request->user()->id) {
            abort(422, 'Pour partir de cet espace, utilisez « Quitter l\'espace ».');
        }

        $adhesion = $this->adhesionDe($espace, $user);

        if ($adhesion->role === RoleMembre::PROPRIETAIRE) {
            abort(422, 'Le propriétaire ne peut pas être retiré de son espace.');
        }

        $acteur = $this->adhesionDe($espace, $request->user());

        if ($acteur->role === RoleMembre::CO_GESTIONNAIRE && $adhesion->role === RoleMembre::CO_GESTIONNAIRE) {
            abort(403, 'Seul le propriétaire peut retirer un co-gestionnaire.');
        }

        $notificationsActives = $adhesion->notifications_actives;

        $adhesion->delete();

        // SCRUM-121 : le membre retiré est prévenu dans l'application.
        if ($notificationsActives) {
            $user->notify(new RetraitEspace($espace, $request->user()));
        }

        return response()->noContent();
    }

    /**
     * POST /api/espaces/{id}/transferer `{ user_id }` (propriétaire) : l'ancien
     * propriétaire devient co-gestionnaire, le membre choisi devient propriétaire.
     */
    public function transferer(Request $request, Espace $espace): JsonResponse
    {
        $this->authorize('delete', $espace); // réservé au propriétaire
        $this->verifierNonArchive($espace);

        $userId = (int) $request->validate([
            'user_id' => ['required', 'integer'],
        ], [
            'user_id.required' => 'Choisissez le nouveau propriétaire.',
        ])['user_id'];

        if ($userId === $request->user()->id) {
            abort(422, 'Vous êtes déjà le propriétaire de cet espace.');
        }

        $nouveau = $espace->adhesions()->where('user_id', $userId)->first();

        if ($nouveau === null) {
            abort(422, "Le nouveau propriétaire doit d'abord être membre de l'espace.");
        }

        $ancien = $this->adhesionDe($espace, $request->user());

        DB::transaction(function () use ($ancien, $nouveau): void {
            // D'abord rétrograder, puis promouvoir : jamais deux propriétaires.
            $ancien->update(['role' => RoleMembre::CO_GESTIONNAIRE]);
            $nouveau->update(['role' => RoleMembre::PROPRIETAIRE]);
        });

        return response()->json(['data' => $this->presenter($nouveau->load('user'))]);
    }

    /**
     * POST /api/espaces/{id}/quitter : interdit pour un groupe organisationnel
     * (rattachement automatique à sa classe). Si le propriétaire part, la
     * propriété revient au plus ancien co-gestionnaire, sinon à un compte de
     * l'administration ; 409 s'il n'y en a aucun.
     */
    public function quitter(Request $request, Espace $espace): Response
    {
        $this->authorize('view', $espace);

        if ($espace->type === TypeEspace::ORGANISATIONNEL) {
            abort(403, 'On ne peut pas quitter le groupe de sa classe : le rattachement est automatique.');
        }

        $user = $request->user();
        $adhesion = $this->adhesionDe($espace, $user);

        if ($adhesion->role !== RoleMembre::PROPRIETAIRE) {
            $adhesion->delete();

            return response()->noContent();
        }

        $successeur = $espace->adhesions()
            ->where('role', RoleMembre::CO_GESTIONNAIRE)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        $administrateur = $successeur === null ? $this->compteAdministration($user) : null;

        if ($successeur === null && $administrateur === null) {
            abort(409, "Impossible de quitter : aucun co-gestionnaire ni compte de l'administration ne peut reprendre l'espace.");
        }

        DB::transaction(function () use ($adhesion, $successeur, $administrateur, $espace): void {
            // Le propriétaire part d'abord, puis le successeur est nommé.
            $adhesion->delete();

            if ($successeur !== null) {
                $successeur->update(['role' => RoleMembre::PROPRIETAIRE]);

                return;
            }

            Adhesion::updateOrCreate(
                ['espace_id' => $espace->id, 'user_id' => $administrateur->id],
                ['role' => RoleMembre::PROPRIETAIRE],
            );
        });

        return response()->noContent();
    }

    /** Adhésion d'un utilisateur à l'espace ; 404 s'il n'en est pas membre. */
    private function adhesionDe(Espace $espace, User $user): Adhesion
    {
        $adhesion = $espace->adhesions()->where('user_id', $user->id)->first();

        if ($adhesion === null) {
            abort(404, "Cette personne n'est pas membre de l'espace.");
        }

        return $adhesion;
    }

    /** Plus ancien compte actif de l'administration (hors l'utilisateur qui part). */
    private function compteAdministration(User $saufLui): ?User
    {
        return User::query()
            ->where('profil', Profil::ADMINISTRATION)
            ->where('etat', EtatCompte::ACTIF)
            ->whereKeyNot($saufLui->id)
            ->orderBy('id')
            ->first();
    }

    private function verifierNonArchive(Espace $espace): void
    {
        if ($espace->etat === EtatEspace::ARCHIVE) {
            abort(403, 'Cet espace est archivé : les rôles ne peuvent plus être modifiés.');
        }
    }

    /** @return array<string, mixed> */
    private function presenter(Adhesion $adhesion): array
    {
        return [
            'user' => [
                'id' => $adhesion->user->id,
                'nom' => $adhesion->user->nom,
                'prenom' => $adhesion->user->prenom,
                'profil' => $adhesion->user->profil?->value,
            ],
            'role' => $adhesion->role->value,
            'depuis' => $adhesion->created_at?->toIso8601String(),
        ];
    }
}
