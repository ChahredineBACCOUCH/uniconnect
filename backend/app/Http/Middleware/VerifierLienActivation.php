<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * SCRUM-66 : vérifie le lien d'activation (signature et expiration).
 *
 * Le lien reçu par e-mail est signé pour la route GET /api/auth/activation/{user}
 * (celle qui ouvre la page Vue d'activation) : sa signature ne peut donc pas
 * être contrôlée par le middleware « signed » de Laravel sur la route
 * POST /api/auth/activate/{user}, qui a un autre chemin.
 *
 * On reconstruit donc le lien d'origine à partir de l'identifiant du compte et
 * de la date d'expiration reçus, puis on délègue la comparaison de la signature
 * et le contrôle de l'expiration à Laravel (URL::hasValidSignature).
 */
class VerifierLienActivation
{
    public function handle(Request $request, Closure $next): Response
    {
        $identifiant = $request->route('user');
        $expires = $request->query('expires');
        $signature = $request->query('signature');

        abort_unless(
            $identifiant && is_numeric($expires) && is_string($signature) && $signature !== '',
            403,
        );

        // Lien d'origine : même route, même compte, même date d'expiration.
        $attendu = Request::create(URL::temporarySignedRoute(
            'activation',
            Carbon::createFromTimestamp((int) $expires),
            ['user' => $identifiant],
        ));

        // 1. La signature reçue est bien celle de ce lien (comparaison à temps constant).
        abort_unless(hash_equals((string) $attendu->query('signature'), $signature), 403);

        // 2. Le lien n'est pas expiré (48 h après l'inscription).
        abort_unless(URL::hasValidSignature($attendu), 403);

        return $next($request);
    }
}
