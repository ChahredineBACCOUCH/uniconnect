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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espace_id')->constrained('espaces')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            // Réponse à un message : la suppression du parent détache la réponse.
            $table->foreignId('parent_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->text('contenu');
            $table->boolean('epingle')->default(false);
            $table->boolean('marque_utile')->default(false);
            $table->boolean('masque')->default(false);
            $table->timestampTz('modifie_le')->nullable();
            // NULL = durée illimitée. Fixé à l'envoi (non rétroactif).
            $table->timestampTz('expire_le')->nullable();
            $table->timestampsTz();
        });

        // Recherche plein texte : colonne calculée + index GIN. PostgreSQL uniquement,
        // SQLite (tests locaux) ne connaissant ni tsvector ni les index GIN.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE messages ADD COLUMN recherche tsvector GENERATED ALWAYS AS (to_tsvector('french', coalesce(contenu, ''))) STORED");
            DB::statement('CREATE INDEX messages_recherche ON messages USING gin (recherche)');
        }

        // Chargement d'une discussion : messages d'un espace, du plus récent au plus ancien.
        DB::statement('CREATE INDEX messages_fil ON messages (espace_id, id DESC)');

        // Purge des messages expirés : index partiel, les messages épinglés sont conservés.
        DB::statement('CREATE INDEX messages_a_purger ON messages (expire_le) WHERE expire_le IS NOT NULL AND NOT epingle');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS messages_recherche');
        DB::statement('DROP INDEX IF EXISTS messages_fil');
        DB::statement('DROP INDEX IF EXISTS messages_a_purger');

        Schema::dropIfExists('messages');
    }
};
