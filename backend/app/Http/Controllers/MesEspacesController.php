<?php

namespace App\Http\Controllers;

use App\Enums\TypeEspace;
use App\Models\Espace;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * SCRUM-85 : espaces de l'utilisateur connecté, pour le menu latéral et l'accueil.
 *
 * GET /api/me/espaces → { organisationnels: [], prives: [], channels: [] }
 *
 * Le nombre de messages non lus est calculé dans la même requête SQL (sous-requête
 * corrélée), sans requête supplémentaire par espace (pas de problème « N+1 »).
 */
class MesEspacesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Messages visibles postés par les autres après le dernier message lu.
        $nonLus = Message::query()
            ->selectRaw('count(*)')
            ->whereColumn('messages.espace_id', 'espaces.id')
            ->whereRaw('messages.id > coalesce(adhesions.dernier_message_lu_id, 0)')
            ->where('messages.masque', false)
            ->where(fn ($q) => $q->whereNull('messages.expire_le')->orWhere('messages.expire_le', '>', now()))
            ->where(fn ($q) => $q->whereNull('messages.auteur_id')->orWhere('messages.auteur_id', '!=', $userId));

        $espaces = Espace::query()
            ->join('adhesions', function ($join) use ($userId) {
                $join->on('adhesions.espace_id', '=', 'espaces.id')
                    ->where('adhesions.user_id', '=', $userId);
            })
            ->select('espaces.*', 'adhesions.role as role')
            ->selectSub($nonLus, 'non_lus')
            ->orderBy('espaces.nom')
            ->get();

        $parType = fn (TypeEspace $type): Collection => $espaces
            ->filter(fn (Espace $e) => $e->type === $type)
            ->map(fn (Espace $e) => $this->resume($e))
            ->values();

        return response()->json([
            'organisationnels' => $parType(TypeEspace::ORGANISATIONNEL),
            'prives' => $parType(TypeEspace::PRIVE),
            'channels' => $parType(TypeEspace::CHANNEL),
        ]);
    }

    /** @return array<string, mixed> */
    private function resume(Espace $espace): array
    {
        return [
            'id' => $espace->id,
            'nom' => $espace->nom,
            'type' => $espace->type->value,
            'icone' => $this->icone($espace),
            'role' => $espace->getAttribute('role'),
            'etat' => $espace->etat?->value,
            'non_lus' => (int) $espace->getAttribute('non_lus'),
            'visibilite_limitee' => $espace->duree_visibilite_heures !== null,
        ];
    }

    /** Icône Material Design du menu (maquette E04). */
    private function icone(Espace $espace): string
    {
        return match ($espace->type) {
            TypeEspace::ORGANISATIONNEL => $espace->matiere_id !== null ? 'mdi-book-outline' : 'mdi-school-outline',
            TypeEspace::PRIVE => 'mdi-lock-outline',
            TypeEspace::CHANNEL => $espace->mode_annonce ? 'mdi-bullhorn-outline' : 'mdi-pound',
        };
    }
}
