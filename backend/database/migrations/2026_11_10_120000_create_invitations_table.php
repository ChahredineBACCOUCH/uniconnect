<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SCRUM-88 : invitations dans un espace (docs/conception/schema.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espace_id')->constrained('espaces')->cascadeOnDelete();
            $table->foreignId('invite_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invite_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('etat', ['EN_ATTENTE', 'ACCEPTEE', 'REFUSEE', 'EXPIREE', 'ANNULEE'])->default('EN_ATTENTE');
            $table->timestampTz('expire_le');
            $table->timestampsTz();

            $table->index('invite_id', 'invitations_invite');
        });

        // Une seule invitation en attente par personne et par espace.
        DB::statement("CREATE UNIQUE INDEX invitation_en_attente_unique ON invitations (espace_id, invite_id) WHERE etat = 'EN_ATTENTE'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invitation_en_attente_unique');

        Schema::dropIfExists('invitations');
    }
};
