<?php

namespace App\Console\Commands;

use App\Enums\EtatInvitation;
use App\Models\Invitation;
use Illuminate\Console\Command;

/**
 * SCRUM-88 : passe à EXPIREE les invitations en attente dont la date est passée.
 * Planifiée chaque jour dans routes/console.php.
 */
class ExpirerInvitations extends Command
{
    protected $signature = 'invitations:expirer';

    protected $description = 'Marque comme expirées les invitations en attente dont la date est dépassée';

    public function handle(): int
    {
        $nombre = Invitation::query()
            ->where('etat', EtatInvitation::EN_ATTENTE)
            ->where('expire_le', '<=', now())
            ->update(['etat' => EtatInvitation::EXPIREE]);

        $this->info("{$nombre} invitation(s) expirée(s).");

        return self::SUCCESS;
    }
}
