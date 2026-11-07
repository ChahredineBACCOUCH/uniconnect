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
        Schema::create('comptes_autorises', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30)->unique();
            $table->string('nom', 80);
            $table->string('prenom', 80);
            $table->string('email', 190)->unique();
            $table->enum('profil', ['ETUDIANT', 'ENSEIGNANT', 'ADMINISTRATION']);
            $table->foreignId('classe_id')->nullable()->constrained('classes')->restrictOnDelete();
            $table->enum('etat', ['NON_CREE', 'EN_ATTENTE', 'ACTIF', 'DESACTIVE', 'SUSPENDU'])
                ->default('NON_CREE');
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['profil', 'classe_id', 'etat'], 'comptes_autorises_filtres');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comptes_autorises');
    }
};
