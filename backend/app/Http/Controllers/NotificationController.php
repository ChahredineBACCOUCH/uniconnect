<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * SCRUM-121 : notifications de l'utilisateur connecté.
 *
 * Les notifications sont enregistrées dans la table `notifications` (canal
 * `database` de Laravel). Elles sont créées par les notifications de
 * `App\Notifications` (invitation, demande d'adhésion, annonce, ressource,
 * signalement traité, retrait d'un espace).
 */
class NotificationController extends Controller
{
    /** Taille de page par défaut de la liste (docs/api.md). */
    private const PAR_PAGE = 20;

    /**
     * Messages en français : l'application n'a pas de fichiers de traduction.
     *
     * @var array<string, string>
     */
    private const MESSAGES = [
        'non_lues.boolean' => 'Le filtre des notifications non lues est invalide.',
        'par_page.integer' => 'Le nombre de lignes par page est invalide.',
        'par_page.min' => 'Le nombre de lignes par page doit être au moins 1.',
        'par_page.max' => 'Le nombre de lignes par page ne peut pas dépasser 100.',
        'page.integer' => 'Le numéro de page est invalide.',
        'page.min' => 'Le numéro de page doit être au moins 1.',
    ];

    /**
     * GET /api/notifications : liste paginée des notifications, les plus
     * récentes d'abord.
     *
     * Filtre `non_lues=1` : seules les notifications pas encore lues.
     * `meta.non_lues` donne le nombre total de notifications non lues.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $donnees = $request->validate([
            'non_lues' => ['sometimes', 'boolean'],
            'par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], self::MESSAGES);

        $utilisateur = $request->user();
        $nonLues = filter_var($donnees['non_lues'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $notifications = $utilisateur->notifications()
            ->when($nonLues, fn ($requete) => $requete->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->paginate($donnees['par_page'] ?? self::PAR_PAGE);

        // Format des listes paginées de docs/api.md : { data, meta: { page, par_page, total } }.
        return NotificationResource::collection($notifications->getCollection())->additional([
            'meta' => [
                'page' => $notifications->currentPage(),
                'par_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'non_lues' => $utilisateur->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/lue : la notification est marquée comme lue.
     *
     * Une notification qui n'est pas la sienne est introuvable (404).
     */
    public function marquerLue(Request $request, string $id): Response
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            abort(404, "Cette notification n'existe pas.");
        }

        $notification->markAsRead();

        return response()->noContent();
    }

    /**
     * POST /api/notifications/tout-lire : toutes les notifications non lues de
     * l'utilisateur sont marquées comme lues.
     */
    public function toutLire(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
