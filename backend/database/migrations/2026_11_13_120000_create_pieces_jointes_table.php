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
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id();
            // La suppression d'un message supprime ses pièces jointes.
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('nom', 255);
            $table->string('mime', 120);
            $table->unsignedBigInteger('taille_octets');
            // Clé de stockage (disque local en v1, S3/R2 plus tard), jamais exposée.
            $table->string('chemin', 500);
            // Même expiration que le message, fixée à l'envoi.
            $table->timestampTz('expire_le')->nullable();
            // La table ne contient pas de `updated_at` (voir le modèle PieceJointe).
            $table->timestampTz('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pieces_jointes');
    }
};
