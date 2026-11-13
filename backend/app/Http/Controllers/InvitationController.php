<?php

namespace App\Http\Controllers;

use App\Enums\EtatEspace;
use App\Enums\EtatInvitation;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Http\Requests\StoreInvitationsRequest;
use App\Http\Resources\EspaceResource;
use App\Http\Resources\InvitationResource;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationRecue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SCRUM-88 : invitations dans un espace (envoyer, lister, accepter, refuser, annuler).
 * Les invitations non traitées expirent (commande `invitations:expirer`, chaque jour).
 */
class InvitationController extends Controller
{
    /**
     * POST /api/espaces/{id}/invitations `{ user_ids: [..] }` (propriétaire ou co-gestionnaire).
     * Les personnes déjà membres ou déjà invitées sont ignorées et signalées.
     */
    public function store(StoreInvitationsRequest $request, Espace $espace): JsonResponse
    {
        $this->authorize('manageMembers', $espace);
        $this->verifierEspaceInvitable($espace);

        $userIds = collect($request->validated('user_ids'))->map(fn ($id) => (int) $id);

        $dejaMembres = $espace->adhesions()->whereIn('user_id', $userIds)->pluck('user_id');

        // Une invitation « en attente » mais dont la date est passée n'a pas encore été
        // balayée par la commande quotidienne : on la clôt pour pouvoir réinviter.
        Invitation::query()
            ->where('espace_id', $espace->id)
            ->whereIn('invite_id', $userIds)
            ->where('etat', EtatInvitation::EN_ATTENTE)
            ->where('expire_le', '<=', now())
            ->update(['etat' => EtatInvitation::EXPIREE]);

        $dejaInvites = Invitation::query()
            ->where('espace_id', $espace->id)
            ->whereIn('invite_id', $userIds)
            ->valides()
            ->pluck('invite_id');

        $aInviter = $userIds->diff($dejaMembres)->diff($dejaInvites)->values();
        $expireLe = now()->addDays((int) config('uniconnect.delai_invitation_jours'));

        DB::transaction(function () use ($aInviter, $espace, $request, $expireLe): void {
            foreach ($aInviter as $userId) {
                Invitation::create([
                    'espace_id' => $espace->id,
                    'invite_id' => $userId,
                    'invite_par_id' => $request->user()->id,
                    'etat' => EtatInvitation::EN_ATTENTE,
                    'expire_le' => $expireLe,
                ]);
            }
        });

        // SCRUM-121 : l'invité est prévenu dans l'application.
        $this->prevenirLesInvites($aInviter, $espace, $request->user());

        return response()->json([
            'creees' => $aInviter->count(),
            'deja_membres' => $dejaMembres->values(),
            'deja_invites' => $dejaInvites->values(),
        ], 201);
    }

    /**
     * GET /api/espaces/{id}/invitations : invitations en attente (gestionnaires),
     * pour pouvoir les annuler depuis l'écran Membres (E07).
     */
    public function indexEspace(Espace $espace): AnonymousResourceCollection
    {
        $this->authorize('manageMembers', $espace);

        return InvitationResource::collection(
            Invitation::query()
                ->where('espace_id', $espace->id)
                ->valides()
                ->with(['invite', 'invitePar'])
                ->orderBy('created_at')
                ->get(),
        );
    }

    /** GET /api/me/invitations : invitations reçues, encore en attente. */
    public function mesInvitations(Request $request): AnonymousResourceCollection
    {
        return InvitationResource::collection(
            Invitation::query()
                ->where('invite_id', $request->user()->id)
                ->valides()
                ->with(['espace', 'invitePar'])
                ->latest()
                ->get(),
        );
    }

    /** POST /api/invitations/{id}/accepter : l'invité devient MEMBRE de l'espace. */
    public function accepter(Request $request, Invitation $invitation): EspaceResource
    {
        $this->verifierInvite($request, $invitation);
        $this->verifierEnAttente($invitation);

        $espace = $invitation->espace;

        if ($espace->etat === EtatEspace::ARCHIVE) {
            abort(422, "Cet espace est archivé : l'invitation ne peut plus être acceptée.");
        }

        DB::transaction(function () use ($invitation, $espace): void {
            // Déjà membre entre-temps (ajouté par un autre chemin) : on garde son rôle.
            Adhesion::firstOrCreate(
                ['espace_id' => $espace->id, 'user_id' => $invitation->invite_id],
                ['role' => RoleMembre::MEMBRE],
            );

            $invitation->update(['etat' => EtatInvitation::ACCEPTEE]);
        });

        return EspaceResource::pour($espace, $request->user());
    }

    /** POST /api/invitations/{id}/refuser */
    public function refuser(Request $request, Invitation $invitation): Response
    {
        $this->verifierInvite($request, $invitation);
        $this->verifierEnAttente($invitation);

        $invitation->update(['etat' => EtatInvitation::REFUSEE]);

        return response()->noContent();
    }

    /** DELETE /api/invitations/{id} : annulée par son auteur ou par le propriétaire de l'espace. */
    public function destroy(Request $request, Invitation $invitation): Response
    {
        $user = $request->user();

        $estAuteur = $invitation->invite_par_id === $user->id;
        $estProprietaire = $invitation->espace->adhesions()
            ->where('user_id', $user->id)
            ->where('role', RoleMembre::PROPRIETAIRE)
            ->exists();

        if (! $estAuteur && ! $estProprietaire) {
            abort(403, "Seul l'auteur de l'invitation ou le propriétaire de l'espace peut l'annuler.");
        }

        if ($invitation->etat !== EtatInvitation::EN_ATTENTE) {
            abort(422, "Cette invitation n'est plus en attente : elle ne peut pas être annulée.");
        }

        $invitation->update(['etat' => EtatInvitation::ANNULEE]);

        return response()->noContent();
    }

    /**
     * SCRUM-121 : chaque personne invitée reçoit une notification.
     *
     * @param  Collection<int, int>  $invites
     */
    private function prevenirLesInvites($invites, Espace $espace, User $auteur): void
    {
        foreach ($invites as $userId) {
            $invite = User::query()->find($userId);

            if ($invite !== null) {
                $invite->notify(new InvitationRecue($espace, $auteur));
            }
        }
    }

    private function verifierEspaceInvitable(Espace $espace): void
    {
        if ($espace->etat === EtatEspace::ARCHIVE) {
            abort(403, 'Cet espace est archivé : impossible d\'inviter de nouveaux membres.');
        }

        if ($espace->type === TypeEspace::ORGANISATIONNEL) {
            abort(422, "Les membres d'un groupe organisationnel sont ajoutés automatiquement : on ne peut pas y inviter.");
        }
    }

    private function verifierInvite(Request $request, Invitation $invitation): void
    {
        if ($invitation->invite_id !== $request->user()->id) {
            abort(403, 'Cette invitation ne vous est pas destinée.');
        }
    }

    /** L'invitation doit être en attente et pas encore expirée (sinon 422). */
    private function verifierEnAttente(Invitation $invitation): void
    {
        if ($invitation->etat === EtatInvitation::EN_ATTENTE && $invitation->estExpiree()) {
            $invitation->update(['etat' => EtatInvitation::EXPIREE]);
        }

        if ($invitation->etat === EtatInvitation::EXPIREE) {
            abort(422, 'Cette invitation a expiré. Demandez une nouvelle invitation.');
        }

        if ($invitation->etat !== EtatInvitation::EN_ATTENTE) {
            abort(422, "Cette invitation n'est plus valable.");
        }
    }
}
