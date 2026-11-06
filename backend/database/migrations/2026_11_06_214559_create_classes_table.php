<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->string('libelle', 120);
            $table->foreignId('filiere_id')->constrained('filieres')->restrictOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('annee_id')->constrained('annees_universitaires')->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestampsTz();

            $table->unique(['code', 'annee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
