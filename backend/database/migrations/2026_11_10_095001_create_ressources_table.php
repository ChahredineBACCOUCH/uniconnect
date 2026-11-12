<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ressources d'un chapitre : fichier (PDF…) ou lien (SCRUM-97, version minimale).
 * Mansour la complète au sprint 2 (upload et téléchargement, SCRUM-126).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ressources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapitre_id')->constrained('chapitres')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre', 160);
            $table->text('description')->nullable();
            $table->string('chemin_fichier', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('mime', 120)->nullable();
            // Passe à true quand le job IndexerRessource a créé les fragments (SCRUM-98).
            $table->boolean('indexee_ia')->default(false);
            $table->timestampsTz();
        });

        // Une ressource est un fichier ou un lien. SQLite (tests locaux) ne sait pas ajouter un CHECK après coup.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ressources ADD CHECK (chemin_fichier IS NOT NULL OR url IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ressources');
    }
};
