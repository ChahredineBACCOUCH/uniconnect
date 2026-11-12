<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chapitres d'un espace : ils classent les ressources du cours (SCRUM-97).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapitres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espace_id')->constrained('espaces')->cascadeOnDelete();
            $table->string('titre', 160);
            $table->smallInteger('ordre')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapitres');
    }
};
