<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SCRUM-82 : un groupe de matière couvre toutes les classes de l'année.
     *
     * Un groupe organisationnel doit donc viser soit une classe, soit une
     * matière, mais plus nécessairement les deux. L'index d'unicité utilise
     * COALESCE sur les deux colonnes pour rester idempotent malgré les NULL.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS espace_orga_unique');
        DB::statement(
            "CREATE UNIQUE INDEX espace_orga_unique ON espaces (annee_id, COALESCE(classe_id, 0), COALESCE(matiere_id, 0)) WHERE type = 'ORGANISATIONNEL'"
        );

        if (DB::getDriverName() === 'pgsql') {
            // La contrainte CHECK d'origine a été ajoutée sans nom : on la
            // retrouve par sa définition avant de la remplacer.
            DB::statement(<<<'SQL'
                DO $$
                DECLARE
                    contrainte record;
                BEGIN
                    FOR contrainte IN
                        SELECT conname
                        FROM pg_constraint
                        WHERE conrelid = 'espaces'::regclass
                          AND contype = 'c'
                          AND pg_get_constraintdef(oid) LIKE '%ORGANISATIONNEL%'
                    LOOP
                        EXECUTE format('ALTER TABLE espaces DROP CONSTRAINT %I', contrainte.conname);
                    END LOOP;
                END
                $$
            SQL);

            DB::statement(
                "ALTER TABLE espaces ADD CONSTRAINT espaces_orga_cible CHECK (type <> 'ORGANISATIONNEL' OR (annee_id IS NOT NULL AND (classe_id IS NOT NULL OR matiere_id IS NOT NULL)))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS espace_orga_unique');
        DB::statement(
            "CREATE UNIQUE INDEX espace_orga_unique ON espaces (annee_id, classe_id, COALESCE(matiere_id, 0)) WHERE type = 'ORGANISATIONNEL'"
        );

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE espaces DROP CONSTRAINT IF EXISTS espaces_orga_cible');
            DB::statement(
                "ALTER TABLE espaces ADD CONSTRAINT espaces_orga_classe_obligatoire CHECK (type <> 'ORGANISATIONNEL' OR (annee_id IS NOT NULL AND classe_id IS NOT NULL))"
            );
        }
    }
};
