<?php

namespace App\Jobs;

use App\Models\Ressource;
use App\Services\IndexationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Indexe une ressource en arrière-plan (SCRUM-98) : lancé par RessourceObserver
 * quand un enseignant ajoute ou remplace un fichier. En local : php artisan queue:work.
 */
class IndexerRessource implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // Laisse le temps d'extraire un gros PDF.
    public int $timeout = 120;

    public function __construct(public Ressource $ressource) {}

    public function handle(IndexationService $indexation): void
    {
        $indexation->indexer($this->ressource);
    }
}
