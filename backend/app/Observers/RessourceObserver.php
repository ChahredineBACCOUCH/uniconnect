<?php

namespace App\Observers;

use App\Jobs\IndexerRessource;
use App\Models\Ressource;
use App\Services\IndexationService;

/**
 * Lance l'indexation IA quand un fichier est ajouté ou remplacé (SCRUM-98).
 * Les fragments sont supprimés avec la ressource par la base (ON DELETE CASCADE).
 */
class RessourceObserver
{
    public function __construct(private IndexationService $indexation) {}

    public function created(Ressource $ressource): void
    {
        $this->lancer($ressource);
    }

    public function updated(Ressource $ressource): void
    {
        if ($ressource->wasChanged('chemin_fichier')) {
            $this->lancer($ressource);
        }
    }

    private function lancer(Ressource $ressource): void
    {
        if ($this->indexation->estIndexable($ressource)) {
            // Après le commit : le job doit trouver la ressource en base.
            IndexerRessource::dispatch($ressource)->afterCommit();
        }
    }
}
