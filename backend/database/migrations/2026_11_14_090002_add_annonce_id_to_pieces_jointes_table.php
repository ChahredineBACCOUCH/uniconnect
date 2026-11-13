<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-124 : une pièce jointe appartient à un message ou à une annonce.
     */
    public function up(): void
    {
        Schema::table('pieces_jointes', function (Blueprint $table) {
            // Une annonce n'a pas de pièce jointe : la colonne devient facultative.
            $table->foreignId('message_id')->nullable()->change();
            // La suppression d'une annonce supprime ses pièces jointes.
            $table->foreignId('annonce_id')
                ->nullable()
                ->after('message_id')
                ->constrained('annonces')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pieces_jointes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annonce_id');
            $table->foreignId('message_id')->nullable(false)->change();
        });
    }
};
