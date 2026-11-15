<?php

namespace App\Console\Commands;

use Database\Seeders\DemoProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Remet la base à l'état de la démonstration : TOUT est effacé (tables et fichiers
 * envoyés), puis DemoProductionSeeder recrée les comptes et le contenu de démo.
 *
 * IRRÉVERSIBLE. La commande refuse de s'exécuter sans la confirmation exacte :
 *   php artisan demo:reinitialiser --confirmer=EFFACER-TOUT
 *
 * En production (pas de Shell Render en offre gratuite), elle est lancée au démarrage
 * du conteneur quand RESET_DEMO=EFFACER-TOUT (voir docker/start.sh). Retirer la
 * variable juste après, sinon la base est effacée à chaque redémarrage.
 */
class ReinitialiserDemo extends Command
{
    public const CONFIRMATION = 'EFFACER-TOUT';

    /** Dossiers du disque où l'application range les fichiers envoyés. */
    private const DOSSIERS_FICHIERS = ['espaces', 'ia-eval'];

    protected $signature = 'demo:reinitialiser {--confirmer= : Saisir EFFACER-TOUT pour confirmer}';

    protected $description = 'Efface toute la base et les fichiers, puis recharge uniquement les données de démonstration';

    public function handle(): int
    {
        if ($this->option('confirmer') !== self::CONFIRMATION) {
            $this->error('Refusé : cette commande efface TOUTES les données. Relancez avec --confirmer='.self::CONFIRMATION.'.');

            return self::FAILURE;
        }

        $this->warn('Réinitialisation : suppression de toutes les tables…');
        $this->call('migrate:fresh', ['--force' => true]);

        foreach (self::DOSSIERS_FICHIERS as $dossier) {
            try {
                Storage::deleteDirectory($dossier);
                $this->line("Fichiers supprimés : {$dossier}/");
            } catch (Throwable $e) {
                // Des fichiers orphelins ne gênent pas la démo : on continue.
                $this->warn("Fichiers de {$dossier}/ non supprimés : {$e->getMessage()}");
            }
        }

        $this->call('db:seed', ['--class' => DemoProductionSeeder::class, '--force' => true]);
        $this->info('Base réinitialisée : seules les données de démonstration sont présentes.');

        return self::SUCCESS;
    }
}
