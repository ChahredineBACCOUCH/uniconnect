<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SCRUM-125 : complète les tables chapitres et ressources créées au sprint 1.
 *
 * - `chapitres.ordre` existe déjà dans la migration initiale ; elle n'est
 *   ajoutée que si une base plus ancienne ne l'a pas ;
 * - `ressources` reçoit les colonnes manquantes (auteur, description, mime,
 *   taille) ;
 * - la contrainte devient exclusive : une ressource est un fichier OU un lien,
 *   jamais les deux.
 *
 * L'ancienne migration n'est pas modifiée : elle a déjà tourné en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chapitres', 'ordre')) {
            Schema::table('chapitres', function (Blueprint $table) {
                $table->smallInteger('ordre')->default(0);
            });
        }

        Schema::table('ressources', function (Blueprint $table) {
            if (! Schema::hasColumn('ressources', 'auteur_id')) {
                $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('ressources', 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn('ressources', 'mime')) {
                $table->string('mime', 120)->nullable();
            }

            if (! Schema::hasColumn('ressources', 'taille')) {
                // Taille du fichier en octets ; NULL pour un lien.
                $table->unsignedBigInteger('taille')->nullable();
            }
        });

        // L'ancienne contrainte autorisait un fichier ET un lien. SQLite ne sait
        // pas ajouter de CHECK après coup : la règle n'est posée que sur
        // PostgreSQL (CI et production).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ressources DROP CONSTRAINT IF EXISTS ressources_check');
            DB::statement('ALTER TABLE ressources DROP CONSTRAINT IF EXISTS ressource_fichier_ou_lien');
            DB::statement('ALTER TABLE ressources ADD CONSTRAINT ressource_fichier_ou_lien CHECK ((chemin_fichier IS NULL) <> (url IS NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ressources DROP CONSTRAINT IF EXISTS ressource_fichier_ou_lien');
            DB::statement('ALTER TABLE ressources ADD CHECK (chemin_fichier IS NOT NULL OR url IS NOT NULL)');
        }

        // Seule `taille` est ajoutée par cette migration sur une base à jour ;
        // les autres colonnes viennent de la migration initiale du sprint 1.
        if (Schema::hasColumn('ressources', 'taille')) {
            Schema::table('ressources', function (Blueprint $table) {
                $table->dropColumn('taille');
            });
        }
    }
};
