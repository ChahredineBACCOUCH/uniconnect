<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * SCRUM-66 : lien d'activation signé, valable 48 h.
 *
 * Le lien est calculé pour la route GET /api/auth/activation/{user} (celle qui
 * ouvre la page Vue d'activation), puis son préfixe est réécrit pour pointer
 * vers la page Vue /activation/{id} : la signature reste celle du backend.
 *
 * La même fonction sert à l'inscription (premier envoi) et au renvoi du lien.
 */
class LienActivation
{
    public static function pour(User $user): string
    {
        $signe = URL::temporarySignedRoute('activation', now()->addHours(48), ['user' => $user->id]);

        return Str::replace(
            URL::to('/api/auth/activation'),
            rtrim((string) config('app.frontend_url'), '/').'/activation',
            $signe,
        );
    }
}
