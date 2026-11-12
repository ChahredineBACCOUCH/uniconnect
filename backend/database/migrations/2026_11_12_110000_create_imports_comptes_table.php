<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-76 : historique des imports de comptes autorisés.
     *
     * Chaque validation d'import laisse une trace (auteur, fichier, lignes
     * importées ou ignorées et erreurs détectées).
     */
    public function up(): void
    {
        Schema::create('imports_comptes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fichier', 255)->nullable();
            $table->unsignedInteger('total_lignes');
            $table->unsignedInteger('importes');
            $table->unsignedInteger('ignores');
            $table->json('erreurs')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports_comptes');
    }
};
