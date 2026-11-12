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
        Schema::create('adhesions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espace_id')->constrained('espaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['PROPRIETAIRE', 'CO_GESTIONNAIRE', 'MEMBRE'])->default('MEMBRE');
            $table->boolean('notifications_actives')->default(true);
            // Compteur de non-lus : simple bigint, sans clé étrangère vers messages
            // (la table messages est créée plus tard, et schema.sql ne la référence pas).
            $table->unsignedBigInteger('dernier_message_lu_id')->nullable();
            $table->timestampsTz();

            $table->unique(['espace_id', 'user_id']);
            $table->index('user_id', 'adhesions_user');
        });

        // Un espace n'a qu'un seul propriétaire.
        DB::statement("CREATE UNIQUE INDEX un_proprietaire_par_espace ON adhesions (espace_id) WHERE role = 'PROPRIETAIRE'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS un_proprietaire_par_espace');

        Schema::dropIfExists('adhesions');
    }
};
