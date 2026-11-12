<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une question posée à l'IA et sa réponse (SCRUM-101).
 * Sert à l'historique, aux avis 👍/👎 (SCRUM-146) et au suivi du quota gratuit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('echanges_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations_ia')->cascadeOnDelete();
            $table->text('question');
            $table->text('reponse')->nullable();
            $table->boolean('avis_utile')->nullable();
            $table->integer('tokens_entree')->nullable();
            $table->integer('tokens_sortie')->nullable();
            $table->integer('duree_ms')->nullable();
            // gemini | groq : l'IA qui a réellement répondu.
            $table->string('fournisseur', 20)->nullable();
            // OK | SANS_SOURCE | ERREUR (enum PHP App\Enums\StatutEchangeIa).
            $table->string('statut', 20)->default('OK');
            // Pas d'updated_at : un échange n'est jamais modifié, sauf son avis.
            $table->timestampTz('created_at')->useCurrent();

            // Compte des questions du jour pour le quota de 20 par étudiant (SCRUM-145).
            $table->index('created_at', 'echanges_quota');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('echanges_ia');
    }
};
