<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de l'Assistant IA d'un espace : 0 ou 1 ligne par espace (SCRUM-97).
 * Activé par le propriétaire de l'espace (SCRUM-145).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistants_ia', function (Blueprint $table) {
            $table->foreignId('espace_id')->primary()->constrained('espaces')->cascadeOnDelete();
            $table->boolean('actif')->default(false);
            // gemini (principal) | groq (secours) : offres gratuites.
            $table->string('fournisseur', 20)->default('gemini');
            $table->string('modele', 60)->nullable();
            // Consigne de l'enseignant ajoutée au prompt système.
            $table->text('instructions')->nullable();
            $table->boolean('citer_sources')->default(true);
            $table->timestampTz('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistants_ia');
    }
};
