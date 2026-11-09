<?php

namespace App\Http\Controllers;

use App\Enums\EtatCompte;
use App\Http\Requests\RegisterRequest;
use App\Mail\ActivationCompte;
use App\Models\CompteAutorise;
use App\Models\User;
use App\Services\EligibiliteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * SCRUM-62/63 : création du compte (pas encore actif) et envoi de l'e-mail
 * d'activation. Le compte passe à EN_ATTENTE, il sera actif après le clic sur
 * le lien signé (SCRUM-66).
 */
class RegisterController extends Controller
{
    /**
     * Messages français pour chaque code de refus.
     *
     * @var array<string, string>
     */
    private const MESSAGES_REFUS = [
        EligibiliteService::PLATEFORME_FERMEE => "La plateforme est fermée : aucune année universitaire n'est active, la création de compte est momentanément indisponible.",
        EligibiliteService::NON_AUTORISE => "Ce matricule n'est pas autorisé à créer un compte : le couple matricule et adresse e-mail ne figure pas dans la liste des comptes autorisés.",
        EligibiliteService::DEJA_INSCRIT => "Un compte a déjà été créé pour ce matricule : l'inscription a déjà été effectuée.",
    ];

    /**
     * POST /api/auth/register : contrôle d'éligibilité, création du compte en
     * attente d'activation, puis envoi de l'e-mail signé (48 h).
     */
    public function store(RegisterRequest $request, EligibiliteService $eligibilite): JsonResponse
    {
        $donnees = $request->validated();

        $code = $eligibilite->verifier($donnees['matricule'], $donnees['email']);

        if ($code !== null) {
            return $this->refus($code);
        }

        /** @var User $user */
        $user = DB::transaction(function () use ($donnees): User {
            $autorise = CompteAutorise::query()
                ->where('matricule', $donnees['matricule'])
                ->where('email', $donnees['email'])
                ->sole();

            $user = User::create([
                'matricule' => $autorise->matricule,
                // Le formulaire peut compléter la fiche, la liste reste la référence.
                'nom' => $donnees['nom'] ?? $autorise->nom,
                'prenom' => $donnees['prenom'] ?? $autorise->prenom,
                'email' => $autorise->email,
                'password' => null,
                'profil' => $autorise->profil,
                'classe_id' => $autorise->classe_id,
                'etat' => EtatCompte::EN_ATTENTE,
            ]);

            $autorise->update([
                'etat' => EtatCompte::EN_ATTENTE,
                'user_id' => $user->id,
            ]);

            return $user;
        });

        Mail::to($user->email)->send(new ActivationCompte($user, $this->lienActivation($user)));

        return response()->json([
            'message' => 'E-mail envoyé',
        ], 201);
    }

    /**
     * Lien signé valable 48 h : la signature est calculée sur la route backend
     * `activation`, puis l'adresse est réécrite pour pointer vers la page Vue
     * /activation/{id} (le routeur Vue attend un paramètre de chemin).
     */
    private function lienActivation(User $user): string
    {
        $signe = URL::temporarySignedRoute('activation', now()->addHours(48), ['user' => $user->id]);

        // …puis on réécrit uniquement le préfixe : /api/auth/activation/{id} devient
        // /activation/{id}, les paramètres expires et signature sont conservés.
        return Str::replace(
            URL::to('/api/auth/activation'),
            rtrim((string) config('app.frontend_url'), '/').'/activation',
            $signe,
        );
    }

    /** Chaque code de refus devient une réponse 422 avec un message français. */
    private function refus(string $code): JsonResponse
    {
        $message = self::MESSAGES_REFUS[$code];

        return response()->json([
            'message' => $message,
            'code' => $code,
            // Le front affiche l'erreur sous le champ concerné (maquette E02).
            'errors' => $code === EligibiliteService::NON_AUTORISE
                ? ['email' => [$message]]
                : [],
        ], 422);
    }
}
