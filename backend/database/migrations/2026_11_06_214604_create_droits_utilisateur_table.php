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
        Schema::create('droits_utilisateur', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('droit', [
                'GERER_PARAMETRAGE',
                'GERER_UTILISATEURS',
                'CREER_GROUPE_ORGA',
                'CREER_GROUPE_PRIVE',
                'CREER_CHANNEL',
                'MODERER',
                'CONSULTER_TABLEAU_BORD',
            ]);
            $table->timestampTz('accorde_le')->useCurrent();

            $table->primary(['user_id', 'droit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('droits_utilisateur');
    }
};
