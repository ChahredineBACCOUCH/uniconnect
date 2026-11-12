<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extraits du cours cités dans une réponse de l'IA (SCRUM-101).
 * Nécessite la table fragments_document (SCRUM-97) : horodatage placé après elle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources_citees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('echange_id')->constrained('echanges_ia')->cascadeOnDelete();
            // Le fragment peut disparaître (ressource supprimée) : on garde l'extrait cité.
            $table->foreignId('fragment_id')->nullable()->constrained('fragments_document')->nullOnDelete();
            $table->text('extrait')->nullable();
            // Précision 24 = type real de PostgreSQL, comme dans docs/conception/schema.sql.
            $table->float('score', 24)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources_citees');
    }
};
