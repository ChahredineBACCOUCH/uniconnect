<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-79 : les cinq référentiels sont désactivables (`actif`), y compris
     * les niveaux, qui n'avaient pas encore la colonne.
     */
    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->boolean('actif')->default(true)->after('libelle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropColumn('actif');
        });
    }
};
