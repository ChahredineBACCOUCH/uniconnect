<?php

namespace App\Http\Controllers;

use App\Enums\EtatEspace;
use App\Enums\StatutEchangeIa;
use App\Exceptions\IAIndisponibleException;
use App\Http\Requests\ConfigurerAssistantIARequest;
use App\Http\Requests\PoserQuestionIARequest;
use App\Models\AssistantIA;
use App\Models\ConversationIA;
use App\Models\EchangeIA;
use App\Models\Espace;
use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SCRUM-102 : poser une question à l'Assistant IA d'un espace.
 * SCRUM-145 : activer l'assistant (PATCH /api/espaces/{espace}/assistant).
 * SCRUM-146 : avis 👍/👎 sur une réponse (PATCH /api/ia/echanges/{echange}/avis).
 *
 * POST /api/espaces/{espace}/ia/questions  { question, conversation_id? }
 * → { conversation_id, echange_id, reponse, statut, sources, quota_restant }
 *
 * 403 non membre · 404 assistant inactif · 422 question invalide
 * 429 quota du jour atteint · 503 Gemini et Groq indisponibles.
 */
class AssistantIAController extends Controller
{
    public function poser(PoserQuestionIARequest $request, Espace $espace, AssistantService $assistant): JsonResponse
    {
        $this->authorize('view', $espace);
        abort_unless((bool) $espace->assistant?->actif, 404, "L'assistant IA n'est pas activé dans cet espace.");

        $user = $request->user();
        $quota = (int) config('uniconnect.ia.quota_questions_jour');
        $restant = $quota - $this->questionsDuJour($user);

        if ($restant <= 0) {
            // Le quota repart à minuit (heure du serveur).
            $demain = now()->addDay()->startOfDay();

            return response()->json([
                'message' => "Vous avez atteint vos {$quota} questions du jour. Revenez demain.",
                'reessayer_a' => $demain->toIso8601String(),
            ], 429, ['Retry-After' => (string) (int) ceil(now()->diffInSeconds($demain))]);
        }

        $question = $request->validated('question');
        $idConversation = $request->validated('conversation_id');
        $conversation = $this->conversation(
            $idConversation !== null ? (int) $idConversation : null,
            $espace,
            $user,
            $question,
        );

        try {
            $resultat = $assistant->poser($espace, $question);
        } catch (IAIndisponibleException) {
            // L'échec est gardé pour le suivi ; il ne compte pas dans le quota.
            EchangeIA::create([
                'conversation_id' => $conversation->id,
                'question' => $question,
                'statut' => StatutEchangeIa::ERREUR,
            ]);

            return response()->json(['message' => "L'assistant est momentanément indisponible."], 503);
        }

        $echange = DB::transaction(function () use ($conversation, $question, $resultat) {
            $echange = EchangeIA::create([
                'conversation_id' => $conversation->id,
                'question' => $question,
                'reponse' => $resultat->reponse,
                'tokens_entree' => $resultat->ia?->tokensEntree,
                'tokens_sortie' => $resultat->ia?->tokensSortie,
                'duree_ms' => $resultat->dureeMs,
                'fournisseur' => $resultat->ia?->fournisseur,
                'statut' => $resultat->statut,
            ]);

            foreach ($resultat->sources as $source) {
                $echange->sources()->create([
                    'fragment_id' => $source['fragment_id'],
                    'extrait' => $source['extrait'],
                    'score' => $source['score'],
                ]);
            }

            $conversation->touch();

            return $echange;
        });

        return response()->json([
            'conversation_id' => $conversation->id,
            'echange_id' => $echange->id,
            'reponse' => $resultat->reponse,
            'statut' => $resultat->statut->value,
            'sources' => $resultat->sourcesPourApi(),
            'quota_restant' => $restant - 1,
        ]);
    }

    /**
     * Active ou désactive l'assistant et enregistre la consigne de l'enseignant.
     * La ligne assistants_ia est créée au premier appel.
     *
     * PATCH /api/espaces/{espace}/assistant  { actif, instructions? }
     * → { actif, instructions, nb_documents_indexes }
     *
     * 403 non gestionnaire, pas enseignant, ou espace archivé · 422 données invalides.
     */
    public function configurer(ConfigurerAssistantIARequest $request, Espace $espace): JsonResponse
    {
        $this->authorize('configurerAssistant', $espace);
        abort_if($espace->etat === EtatEspace::ARCHIVE, 403, 'Cet espace est archivé : il ne peut plus être modifié.');

        $donnees = ['actif' => $request->boolean('actif')];
        if ($request->has('instructions')) {
            $consigne = trim((string) $request->validated('instructions'));
            $donnees['instructions'] = $consigne === '' ? null : $consigne;
        }

        $assistant = AssistantIA::updateOrCreate(['espace_id' => $espace->id], $donnees);

        return response()->json([
            'actif' => $assistant->actif,
            'instructions' => $assistant->instructions,
            'nb_documents_indexes' => $espace->ressources()->where('indexee_ia', true)->count(),
        ]);
    }

    /**
     * Avis de l'étudiant sur une réponse : utile (👍) ou non (👎). Il peut le changer.
     *
     * PATCH /api/ia/echanges/{echange}/avis  { utile: true|false } → 204
     *
     * 404 si l'échange n'est pas une question de la personne connectée
     * (on ne révèle pas les échanges des autres) · 422 si l'IA n'a pas répondu (ERREUR).
     */
    public function avis(Request $request, EchangeIA $echange): Response
    {
        $donnees = $request->validate(
            ['utile' => ['required', 'boolean']],
            [
                'utile.required' => 'Indiquez si la réponse était utile.',
                'utile.boolean' => 'La valeur « utile » doit être vrai ou faux.',
            ],
        );

        abort_unless($echange->conversation?->user_id === $request->user()->id, 404);
        abort_if(
            $echange->statut === StatutEchangeIa::ERREUR,
            422,
            "Impossible de donner un avis : l'assistant n'a pas répondu à cette question.",
        );

        $echange->update(['avis_utile' => (bool) $donnees['utile']]);

        return response()->noContent();
    }

    /**
     * Questions posées aujourd'hui par l'utilisateur, tous espaces confondus.
     * Les échanges en ERREUR (IA indisponible) ne sont pas comptés.
     */
    private function questionsDuJour(User $user): int
    {
        return EchangeIA::query()
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
            ->where('statut', '!=', StatutEchangeIa::ERREUR->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * Conversation existante de l'utilisateur dans cet espace, ou nouvelle conversation
     * (titre = début de la première question). L'identifiant d'une conversation d'une
     * autre personne ou d'un autre espace donne 404.
     */
    private function conversation(?int $id, Espace $espace, User $user, string $question): ConversationIA
    {
        if ($id !== null) {
            return ConversationIA::query()
                ->where('espace_id', $espace->id)
                ->where('user_id', $user->id)
                ->findOrFail($id);
        }

        return ConversationIA::create([
            'espace_id' => $espace->id,
            'user_id' => $user->id,
            'titre' => Str::limit($question, 157),
        ]);
    }
}
