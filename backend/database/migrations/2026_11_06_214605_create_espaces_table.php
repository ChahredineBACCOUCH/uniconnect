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
        Schema::create('espaces', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['ORGANISATIONNEL', 'PRIVE', 'CHANNEL']);
            $table->string('nom', 120);
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->enum('etat', ['ACTIF', 'ARCHIVE'])->default('ACTIF');
            // NULL = durée illimitée ; sinon nombre d'heures de visibilité des messages.
            $table->integer('duree_visibilite_heures')->nullable();
            $table->boolean('duree_courte_autorisee')->default(false);
            $table->enum('mode_acces', ['OUVERT', 'SUR_DEMANDE', 'RESTREINT'])->nullable();
            $table->boolean('mode_annonce')->default(false);
            $table->jsonb('audience')->nullable();
            $table->foreignId('annee_id')->nullable()->constrained('annees_universitaires')->restrictOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->restrictOnDelete();
            $table->foreignId('matiere_id')->nullable()->constrained('matieres')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['type', 'etat'], 'espaces_type_etat');
        });

        // Contraintes liées au type. SQLite (tests locaux) ne sait pas les ajouter après coup.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE espaces ADD CHECK (type <> 'CHANNEL' OR mode_acces IS NOT NULL)");
            DB::statement("ALTER TABLE espaces ADD CHECK (type <> 'ORGANISATIONNEL' OR (annee_id IS NOT NULL AND classe_id IS NOT NULL))");
        }

        // Génération idempotente : un seul groupe organisationnel par année + classe + matière.
        // COALESCE(matiere_id, 0) car PostgreSQL considère NULL comme distinct dans un index unique.
        DB::statement("CREATE UNIQUE INDEX espace_orga_unique ON espaces (annee_id, classe_id, COALESCE(matiere_id, 0)) WHERE type = 'ORGANISATIONNEL'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS espace_orga_unique');

        Schema::dropIfExists('espaces');
    }
};
