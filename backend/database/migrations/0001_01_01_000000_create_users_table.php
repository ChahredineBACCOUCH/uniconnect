<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30)->unique();
            $table->string('nom', 80);
            $table->string('prenom', 80);
            $table->string('email', 190)->unique();
            $table->string('password', 255)->nullable();
            $table->string('photo', 255)->nullable();
            $table->enum('profil', ['ETUDIANT', 'ENSEIGNANT', 'ADMINISTRATION']);
            // La clé étrangère vers classes est ajoutée après la création de cette table.
            $table->foreignId('classe_id')->nullable();
            $table->enum('etat', ['NON_CREE', 'EN_ATTENTE', 'ACTIF', 'DESACTIVE', 'SUSPENDU'])
                ->default('EN_ATTENTE');
            $table->smallInteger('tentatives_echouees')->default(0);
            $table->timestampTz('bloque_jusqua')->nullable();
            $table->timestampTz('suspendu_jusqua')->nullable();
            $table->timestampTz('derniere_connexion')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });

        // Un étudiant actif doit obligatoirement être rattaché à une classe.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CHECK (profil <> 'ETUDIANT' OR classe_id IS NOT NULL OR etat <> 'ACTIF')");
        }

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
