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
        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 160);
            $table->smallInteger('semestre');
            $table->boolean('actif')->default(true);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE matieres ADD CHECK (semestre BETWEEN 1 AND 2)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matieres');
    }
};
