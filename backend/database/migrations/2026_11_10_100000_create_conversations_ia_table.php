<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversations d'un étudiant avec l'Assistant IA d'un espace (SCRUM-101).
 * Nécessite la table espaces (SCRUM-81) : horodatage volontairement placé après elle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espace_id')->constrained('espaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre', 160)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations_ia');
    }
};
