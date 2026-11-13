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
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            // La suppression d'un espace supprime ses annonces.
            $table->foreignId('espace_id')->constrained('espaces')->cascadeOnDelete();
            // Annonce conservée si le compte de son auteur est supprimé.
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre', 160);
            $table->text('contenu');
            // Case « notifier par e-mail » de l'écran de publication.
            $table->boolean('notifier_email')->default(false);
            $table->timestampsTz();

            // Liste d'un espace : les annonces les plus récentes d'abord.
            $table->index(['espace_id', 'id'], 'annonces_fil');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annonces');
    }
};
