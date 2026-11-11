<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Models\Adhesion;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Departement;
use App\Models\Espace;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SCRUM-81 : tables espaces et adhésions, index uniques et relations Eloquent.
 */
class EspacesAdhesionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_tables_et_colonnes_attendues_existent(): void
    {
        $this->assertTrue(Schema::hasTable('espaces'));
        $this->assertTrue(Schema::hasTable('adhesions'));

        $colonnesEspaces = [
            'id', 'type', 'nom', 'description', 'image', 'etat',
            'duree_visibilite_heures', 'duree_courte_autorisee', 'mode_acces',
            'mode_annonce', 'audience', 'annee_id', 'classe_id', 'matiere_id',
            'created_by', 'created_at', 'updated_at',
        ];

        foreach ($colonnesEspaces as $colonne) {
            $this->assertTrue(Schema::hasColumn('espaces', $colonne), "Colonne espaces.$colonne absente");
        }

        $colonnesAdhesions = [
            'id', 'espace_id', 'user_id', 'role', 'notifications_actives',
            'dernier_message_lu_id', 'created_at', 'updated_at',
        ];

        foreach ($colonnesAdhesions as $colonne) {
            $this->assertTrue(Schema::hasColumn('adhesions', $colonne), "Colonne adhesions.$colonne absente");
        }
    }

    public function test_un_channel_exige_un_mode_acces(): void
    {
        $this->skipSiSqlite();

        $this->expectException(QueryException::class);

        Espace::create(['type' => 'CHANNEL', 'nom' => 'Canal']);
    }

    public function test_un_groupe_organisationnel_exige_une_annee_et_une_classe(): void
    {
        $this->skipSiSqlite();

        $this->expectException(QueryException::class);

        Espace::create(['type' => 'ORGANISATIONNEL', 'nom' => 'Groupe orphelin']);
    }

    public function test_un_groupe_organisationnel_est_unique_par_annee_classe_matiere(): void
    {
        [$annee, $classe, $matiere] = $this->referentiel();

        $attributs = [
            'type' => TypeEspace::ORGANISATIONNEL,
            'nom' => 'Programmation L2 A',
            'annee_id' => $annee->id,
            'classe_id' => $classe->id,
            'matiere_id' => $matiere->id,
        ];

        Espace::create($attributs);

        $this->expectException(QueryException::class);

        Espace::create($attributs);
    }

    public function test_un_groupe_organisationnel_sans_matiere_est_aussi_unique(): void
    {
        [$annee, $classe] = $this->referentiel();

        $attributs = [
            'type' => TypeEspace::ORGANISATIONNEL,
            'nom' => 'Classe L2 Informatique A',
            'annee_id' => $annee->id,
            'classe_id' => $classe->id,
            'matiere_id' => null,
        ];

        Espace::create($attributs);

        $this->expectException(QueryException::class);

        Espace::create($attributs);
    }

    public function test_un_espace_ne_peut_avoir_qu_un_seul_proprietaire(): void
    {
        $espace = Espace::create(['type' => TypeEspace::PRIVE, 'nom' => 'Groupe de révision']);

        Adhesion::create([
            'espace_id' => $espace->id,
            'user_id' => User::factory()->create()->id,
            'role' => RoleMembre::PROPRIETAIRE,
        ]);

        $this->expectException(QueryException::class);

        Adhesion::create([
            'espace_id' => $espace->id,
            'user_id' => User::factory()->create()->id,
            'role' => RoleMembre::PROPRIETAIRE,
        ]);
    }

    public function test_un_utilisateur_ne_peut_adhérer_qu_une_fois_au_même_espace(): void
    {
        $espace = Espace::create(['type' => TypeEspace::PRIVE, 'nom' => 'Groupe de révision']);
        $user = User::factory()->create();

        Adhesion::create([
            'espace_id' => $espace->id,
            'user_id' => $user->id,
            'role' => RoleMembre::MEMBRE,
        ]);

        $this->expectException(QueryException::class);

        Adhesion::create([
            'espace_id' => $espace->id,
            'user_id' => $user->id,
            'role' => RoleMembre::CO_GESTIONNAIRE,
        ]);
    }

    public function test_les_relations_et_les_casts_des_espaces_et_adhesions(): void
    {
        $espace = Espace::create([
            'type' => 'PRIVE',
            'nom' => 'Groupe de révision',
            'description' => 'Préparer l\'examen',
            'duree_visibilite_heures' => 24,
            'duree_courte_autorisee' => true,
            'audience' => ['L2', 'L3'],
        ]);

        $user = User::factory()->create();

        $adhesion = Adhesion::create([
            'espace_id' => $espace->id,
            'user_id' => $user->id,
            'role' => 'PROPRIETAIRE',
            'notifications_actives' => false,
        ]);

        $this->assertSame(TypeEspace::PRIVE, $espace->type);
        $this->assertSame(24, $espace->duree_visibilite_heures);
        $this->assertTrue($espace->duree_courte_autorisee);
        $this->assertSame(['L2', 'L3'], $espace->audience);

        $this->assertSame(RoleMembre::PROPRIETAIRE, $adhesion->role);
        $this->assertFalse($adhesion->notifications_actives);

        $this->assertSame($espace->id, $adhesion->espace->id);
        $this->assertSame($user->id, $adhesion->user->id);

        $this->assertCount(1, $espace->adhesions);
        $this->assertCount(1, $espace->membres);
        $this->assertSame($user->id, $espace->membres->first()->id);
        $this->assertSame('PROPRIETAIRE', $espace->membres->first()->pivot->role);

        $this->assertCount(1, $user->adhesions);
        $this->assertCount(1, $user->espaces);
        $this->assertSame($espace->id, $user->espaces->first()->id);
    }

    /**
     * Les contraintes CHECK sont ajoutées par DB::statement, uniquement sur PostgreSQL.
     */
    private function skipSiSqlite(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Contraintes CHECK testées sur PostgreSQL (comme la CI).');
        }
    }

    /**
     * Référentiel minimal pour créer un groupe organisationnel.
     *
     * @return array{AnneeUniversitaire, Classe, Matiere}
     */
    private function referentiel(): array
    {
        $annee = AnneeUniversitaire::create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => false,
        ]);

        $departement = Departement::create(['code' => 'INFO', 'libelle' => 'Informatique']);

        $filiere = Filiere::create([
            'code' => 'GL',
            'libelle' => 'Génie Logiciel',
            'departement_id' => $departement->id,
        ]);

        $niveau = Niveau::create(['code' => 'L2', 'libelle' => 'Licence 2']);

        $classe = Classe::create([
            'code' => 'L2-INFO-A',
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ]);

        $matiere = Matiere::create([
            'code' => 'PROG',
            'libelle' => 'Programmation',
            'semestre' => 1,
        ]);

        return [$annee, $classe, $matiere];
    }
}
