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
        Schema::create('annees_universitaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 9)->unique();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('active')->default(false);
            $table->timestampsTz();
        });

        // SQLite (tests locaux) ne sait pas ajouter une contrainte CHECK après coup.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE annees_universitaires ADD CHECK (date_fin > date_debut)');
        }

        // Une seule année universitaire peut être active à la fois.
        DB::statement('CREATE UNIQUE INDEX annee_une_seule_active ON annees_universitaires (active) WHERE active');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS annee_une_seule_active');

        Schema::dropIfExists('annees_universitaires');
    }
};
