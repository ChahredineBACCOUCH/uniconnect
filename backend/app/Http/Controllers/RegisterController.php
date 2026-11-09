<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Services\EligibiliteService;
use Illuminate\Http\JsonResponse;

/**
 * SCRUM-62 : contrôle d'éligibilité avant création de compte.
 * La création du compte lui-même (e-mail d'activation) appartient à SCRUM-63.
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

    public function __invoke(RegisterRequest $request, EligibiliteService $eligibilite): JsonResponse
    {
        $matricule = (string) $request->validated('matricule');
        $email = (string) $request->validated('email');

        $code = $eligibilite->verifier($matricule, $email);

        if ($code !== null) {
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

        return response()->json([
            'message' => 'Vous êtes autorisé à créer un compte.',
        ], 200);
    }
}
