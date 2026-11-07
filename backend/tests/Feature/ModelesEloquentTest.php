<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\CompteAutorise;
use App\Models\Departement;
use App\Models\DroitUtilisateur;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

/**
 * SCRUM-41 : vérifie les tables, relations et casts des modèles sans base de données.
 * Le test avec données (Classe::with('filiere.departement')) se fait dans Tinker après les seeders.
 */
class ModelesEloquentTest extends TestCase
{
    public function test_chaque_modele_pointe_sur_la_bonne_table(): void
    {
        $tables = [
            AnneeUniversitaire::class => 'annees_universitaires',
            Departement::class => 'departements',
            Filiere::class => 'filieres',
            Niveau::class => 'niveaux',
            Classe::class => 'classes',
            Matiere::class => 'matieres',
            CompteAutorise::class => 'comptes_autorises',
            DroitUtilisateur::class => 'droits_utilisateur',
            User::class => 'users',
        ];

        foreach ($tables as $modele => $table) {
            $this->assertSame($table, (new $modele)->getTable(), $modele);
        }
    }

    public function test_relations_du_referentiel_academique(): void
    {
        $classe = new Classe;

        $this->assertRelation($classe->filiere(), BelongsTo::class, Filiere::class, 'filiere_id');
        $this->assertRelation($classe->niveau(), BelongsTo::class, Niveau::class, 'niveau_id');
        $this->assertRelation($classe->annee(), BelongsTo::class, AnneeUniversitaire::class, 'annee_id');
        $this->assertRelation($classe->etudiants(), HasMany::class, User::class, 'classe_id');
        $this->assertRelation($classe->comptesAutorises(), HasMany::class, CompteAutorise::class, 'classe_id');

        $this->assertRelation((new Filiere)->departement(), BelongsTo::class, Departement::class, 'departement_id');
        $this->assertRelation((new Filiere)->classes(), HasMany::class, Classe::class, 'filiere_id');
        $this->assertRelation((new Departement)->filieres(), HasMany::class, Filiere::class, 'departement_id');
        $this->assertRelation((new Niveau)->classes(), HasMany::class, Classe::class, 'niveau_id');
        $this->assertRelation((new AnneeUniversitaire)->classes(), HasMany::class, Classe::class, 'annee_id');

        $matieres = $classe->matieres();
        $this->assertInstanceOf(BelongsToMany::class, $matieres);
        $this->assertSame('classe_matiere', $matieres->getTable());
        $this->assertSame('classe_id', $matieres->getForeignPivotKeyName());
        $this->assertSame('matiere_id', $matieres->getRelatedPivotKeyName());
        $this->assertSame('classe_matiere', (new Matiere)->classes()->getTable());
    }

    public function test_relations_des_utilisateurs(): void
    {
        $user = new User;

        $this->assertRelation($user->classe(), BelongsTo::class, Classe::class, 'classe_id');
        $this->assertRelation($user->compteAutorise(), HasOne::class, CompteAutorise::class, 'user_id');
        $this->assertRelation($user->droits(), HasMany::class, DroitUtilisateur::class, 'user_id');
        $this->assertRelation((new CompteAutorise)->classe(), BelongsTo::class, Classe::class, 'classe_id');
        $this->assertRelation((new CompteAutorise)->user(), BelongsTo::class, User::class, 'user_id');
    }

    public function test_les_colonnes_enum_sont_converties_en_enums_php(): void
    {
        $user = new User(['profil' => 'ENSEIGNANT', 'etat' => 'ACTIF']);
        $this->assertSame(Profil::ENSEIGNANT, $user->profil);
        $this->assertSame(EtatCompte::ACTIF, $user->etat);

        $compte = new CompteAutorise(['profil' => Profil::ETUDIANT, 'etat' => 'NON_CREE']);
        $this->assertSame(Profil::ETUDIANT, $compte->profil);
        $this->assertSame(EtatCompte::NON_CREE, $compte->etat);
        $this->assertSame('ETUDIANT', $compte->getAttributes()['profil']);

        $droit = new DroitUtilisateur(['droit' => 'MODERER']);
        $this->assertSame(Droit::MODERER, $droit->droit);
    }

    public function test_possede_droit_utilise_les_droits_deja_charges(): void
    {
        $user = new User;
        $user->setRelation('droits', collect([new DroitUtilisateur(['droit' => Droit::MODERER])]));

        $this->assertTrue($user->possedeDroit(Droit::MODERER));
        $this->assertFalse($user->possedeDroit(Droit::GERER_UTILISATEURS));
    }

    private function assertRelation(object $relation, string $type, string $modele, string $cle): void
    {
        $this->assertInstanceOf($type, $relation);
        $this->assertInstanceOf($modele, $relation->getRelated());

        $this->assertSame($cle, $relation->getForeignKeyName());
    }
}
