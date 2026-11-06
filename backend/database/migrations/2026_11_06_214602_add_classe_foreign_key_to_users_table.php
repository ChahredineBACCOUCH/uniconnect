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
        // La table classes est créée après users : la clé étrangère est donc
        // ajoutée ici, une fois les deux tables disponibles.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('classe_id')
                ->references('id')
                ->on('classes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['classe_id']);
        });
    }
};
