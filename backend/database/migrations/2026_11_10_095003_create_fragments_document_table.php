<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fragments de texte d'une ressource, dans lesquels l'Assistant IA cherche ses réponses (SCRUM-97).
 * Créés par IndexationService (SCRUM-98). Doit passer avant les migrations IA de SCRUM-101.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragments_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressource_id')->constrained('ressources')->cascadeOnDelete();
            $table->integer('page')->nullable();
            $table->integer('ordre');
            $table->text('contenu');
            $table->timestampTz('created_at')->nullable();

            $table->index(['ressource_id', 'ordre'], 'fragments_ressource');
        });

        // Recherche plein texte en français : colonne calculée + index GIN.
        // PostgreSQL uniquement : SQLite (tests locaux) ne connaît ni tsvector ni les index GIN.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE fragments_document ADD COLUMN recherche tsvector GENERATED ALWAYS AS (to_tsvector('french', contenu)) STORED");
            DB::statement('CREATE INDEX fragments_recherche ON fragments_document USING gin (recherche)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fragments_document');
    }
};
