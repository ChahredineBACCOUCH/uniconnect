<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-79 : matières enseignées par un utilisateur (profil ENSEIGNANT).
     *
     * La table pivot `classe_matiere` existait déjà ; celle-ci complète le lien
     * nécessaire à `classe_ids[]` / `enseignant_ids[]` de l'API.
     */
    public function up(): void
    {
        Schema::create('matiere_enseignant', function (Blueprint $table) {
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['matiere_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matiere_enseignant');
    }
};
