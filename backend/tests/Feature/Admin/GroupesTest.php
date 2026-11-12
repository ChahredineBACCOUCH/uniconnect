<?php

namespace Tests\Feature\Admin;

use App\Enums\Droit;
use App\Enums\EtatEspace;
use App\Enums\ModeAcces;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Models\Adhesion;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Espace;
use App\Models\Matiere;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-83 : écran A06, liste des groupes organisationnels de l'année active
 * avec filtres, synchronisation des membres et archivage.
 */
class GroupesTest extends TestCase
{
    use RefreshDatabase;

    private function preparerDemonstration(): User
    {
        $this->seed(DemoSeeder::class);

        $admin = User::query()->where('email', 'admin@demo.uniconnect')->sole();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function genererGroupes(): void
    {
        $this->postJson('/api/admin/groupes/generer')->assertOk();
    }

    private function groupeDeClasse(string $code): Espace
    {
        $classe = Classe::query()->where('code', $code)->sole();

        return Espace::query()
            ->where('type', TypeEspace::ORGANISATIONNEL->value)
            ->where('classe_id', $classe->id)
            ->sole();
    }

    public function test_la_liste_renvoie_les_groupes_avec_proprietaire_et_membres(): void
    {
        $admin = $this->preparerDemonstration();
        $this->genererGroupes();

        $reponse = $this->getJson('/api/admin/groupes')
            ->assertOk()
            ->assertJsonPath('annee.libelle', '2026/2027')
            ->assertJsonPath('total', 9)
            ->assertJsonCount(9, 'data');

        $groupes = collect($reponse->json('data'));

        // Les groupes de classe sont listés avant ceux de matière.
        $this->assertSame('CLASSE', $groupes->first()['type']);
        $this->assertSame('MATIERE', $groupes->last()['type']);

        $classeA = $groupes->firstWhere('nom', 'L2 Informatique A');
        $this->assertSame('CLASSE', $classeA['type']);
        $this->assertSame('L2-INFO-A', $classeA['classe']['code']);
        $this->assertSame($admin->id, $classeA['proprietaire']['id']);
        $this->assertSame('Fatou Diallo', $classeA['proprietaire']['nom_complet']);
        // Le propriétaire et l'étudiant de démonstration.
        $this->assertSame(2, $classeA['nombre_membres']);

        $matiere = $groupes->firstWhere('nom', 'Algorithmique avancée');
        $this->assertSame('MATIERE', $matiere['type']);
        $this->assertNull($matiere['classe']);
        $this->assertSame('INF201', $matiere['matiere']['code']);
        // Un groupe de matière couvre les trois classes de l'année.
        $this->assertSame(
            ['L2-INFO-A', 'L2-INFO-B', 'L3-INFO'],
            collect($matiere['classes'])->pluck('code')->all(),
        );
        $this->assertSame(2, $matiere['nombre_membres']);
        $this->assertSame('ACTIF', $matiere['etat']);
    }

    public function test_le_filtre_par_classe_inclut_les_groupes_de_matiere_de_cette_classe(): void
    {
        $this->preparerDemonstration();
        $this->genererGroupes();

        $classe = Classe::query()->where('code', 'L3-INFO')->sole();

        $reponse = $this->getJson('/api/admin/groupes?classe_id='.$classe->id)
            ->assertOk()
            ->assertJsonPath('total', 7)
            ->assertJsonCount(7, 'data');

        $groupes = collect($reponse->json('data'));
        $this->assertSame(1, $groupes->where('type', 'CLASSE')->count());
        $this->assertSame('L3-INFO', $groupes->firstWhere('type', 'CLASSE')['classe']['code']);
        $this->assertSame(6, $groupes->where('type', 'MATIERE')->count());
        $this->assertTrue($groupes->every(
            fn (array $groupe): bool => collect($groupe['classes'])->pluck('code')->contains('L3-INFO'),
        ));
    }

    public function test_les_filtres_par_filiere_et_par_niveau(): void
    {
        $this->preparerDemonstration();
        $this->genererGroupes();

        $filiere = Classe::query()->where('code', 'L3-INFO')->sole()->filiere;
        $niveau = Classe::query()->where('code', 'L2-INFO-B')->sole()->niveau;

        // Filière GL : deux classes (L2-INFO-A et L3-INFO) + les six matières.
        $reponse = $this->getJson('/api/admin/groupes?filiere_id='.$filiere->id)
            ->assertOk()
            ->assertJsonPath('total', 8)
            ->assertJsonCount(8, 'data');

        $groupes = collect($reponse->json('data'));
        $this->assertSame(2, $groupes->where('type', 'CLASSE')->count());
        $this->assertSame(6, $groupes->where('type', 'MATIERE')->count());

        // Niveau L2 : deux classes (A et B) + les six matières.
        $reponse = $this->getJson('/api/admin/groupes?niveau_id='.$niveau->id)
            ->assertOk()
            ->assertJsonPath('total', 8)
            ->assertJsonCount(8, 'data');

        $groupes = collect($reponse->json('data'));
        $this->assertSame(2, $groupes->where('type', 'CLASSE')->count());
        $this->assertSame(6, $groupes->where('type', 'MATIERE')->count());
    }

    public function test_le_filtre_par_enseignant_retient_les_groupes_dont_il_est_proprietaire(): void
    {
        $this->preparerDemonstration();

        $enseignant = User::query()->where('email', 'enseignant@demo.uniconnect')->sole();
        $matiere = Matiere::query()->where('code', 'INF201')->sole();
        $matiere->enseignants()->attach($enseignant->id);

        $this->genererGroupes();

        $reponse = $this->getJson('/api/admin/groupes?enseignant_id='.$enseignant->id)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data');

        $this->assertSame('Algorithmique avancée', $reponse->json('data.0.nom'));
        $this->assertSame($enseignant->id, $reponse->json('data.0.proprietaire.id'));
    }

    public function test_les_filtres_invalides_sont_refuses(): void
    {
        $this->preparerDemonstration();

        $this->getJson('/api/admin/groupes?classe_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('classe_id');
    }

    public function test_les_options_de_filtres_de_l_annee_active(): void
    {
        $this->preparerDemonstration();

        $reponse = $this->getJson('/api/admin/groupes/filtres')
            ->assertOk()
            ->assertJsonPath('annee.libelle', '2026/2027')
            ->assertJsonCount(2, 'filieres')
            ->assertJsonCount(2, 'niveaux')
            ->assertJsonCount(3, 'classes')
            ->assertJsonCount(1, 'enseignants');

        $this->assertSame('L2-INFO-A', $reponse->json('classes.0.code'));
        $this->assertSame('Sophie Martin', $reponse->json('enseignants.0.nom_complet'));
    }

    public function test_synchroniser_ajoute_les_membres_manquants(): void
    {
        $this->preparerDemonstration();
        $this->genererGroupes();

        $etudiant = User::query()->where('email', 'etudiant@demo.uniconnect')->sole();
        $groupe = $this->groupeDeClasse('L2-INFO-A');

        // Simule un membre manquant : le rattachement est relancé.
        Adhesion::query()
            ->where('espace_id', $groupe->id)
            ->where('user_id', $etudiant->id)
            ->delete();

        $this->assertSame(1, $groupe->adhesions()->count());

        $this->postJson('/api/admin/groupes/'.$groupe->id.'/synchroniser')
            ->assertOk()
            ->assertJsonPath('membres_ajoutes', 1)
            ->assertJsonPath('groupe.id', $groupe->id)
            ->assertJsonPath('groupe.nombre_membres', 2);

        $this->assertTrue(
            $groupe->adhesions()
                ->where('user_id', $etudiant->id)
                ->where('role', RoleMembre::MEMBRE->value)
                ->exists()
        );
    }

    public function test_synchroniser_un_groupe_a_jour_n_ajoute_rien(): void
    {
        $this->preparerDemonstration();
        $this->genererGroupes();

        $groupe = $this->groupeDeClasse('L2-INFO-A');

        $this->postJson('/api/admin/groupes/'.$groupe->id.'/synchroniser')
            ->assertOk()
            ->assertJsonPath('membres_ajoutes', 0);
    }

    public function test_archiver_puis_synchroniser_un_groupe_archive_repond_409(): void
    {
        $this->preparerDemonstration();
        $this->genererGroupes();

        $groupe = $this->groupeDeClasse('L2-INFO-A');

        $this->postJson('/api/admin/groupes/'.$groupe->id.'/archiver')
            ->assertOk()
            ->assertJsonPath('groupe.id', $groupe->id)
            ->assertJsonPath('groupe.etat', EtatEspace::ARCHIVE->value);

        $this->assertSame(EtatEspace::ARCHIVE, $groupe->refresh()->etat);

        // L'archivage est idempotent.
        $this->postJson('/api/admin/groupes/'.$groupe->id.'/archiver')
            ->assertOk()
            ->assertJsonPath('groupe.etat', EtatEspace::ARCHIVE->value);

        $this->postJson('/api/admin/groupes/'.$groupe->id.'/synchroniser')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Un groupe archivé ne peut pas être synchronisé.');
    }

    public function test_les_actions_sont_refusees_sur_un_espace_non_organisationnel(): void
    {
        $this->preparerDemonstration();

        $prive = Espace::query()->create([
            'type' => TypeEspace::PRIVE->value,
            'nom' => 'Groupe privé',
            'etat' => EtatEspace::ACTIF->value,
            'mode_acces' => ModeAcces::RESTREINT->value,
        ]);

        $this->postJson('/api/admin/groupes/'.$prive->id.'/archiver')->assertNotFound();
        $this->postJson('/api/admin/groupes/'.$prive->id.'/synchroniser')->assertNotFound();
    }

    public function test_sans_annee_active_la_liste_est_vide_sans_erreur(): void
    {
        $this->preparerDemonstration();

        AnneeUniversitaire::query()->update(['active' => false]);

        $this->getJson('/api/admin/groupes')
            ->assertOk()
            ->assertJsonPath('annee', null)
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/admin/groupes/filtres')
            ->assertOk()
            ->assertJsonPath('annee', null)
            ->assertJsonCount(0, 'filieres')
            ->assertJsonCount(0, 'classes')
            ->assertJsonCount(0, 'enseignants');
    }

    public function test_un_utilisateur_sans_le_droit_ne_peut_pas_consulter_les_groupes(): void
    {
        $admin = $this->preparerDemonstration();
        $this->genererGroupes();

        $groupe = Espace::query()->firstOrFail();

        $admin->retirerDroit(Droit::CREER_GROUPE_ORGA);

        $this->getJson('/api/admin/groupes')->assertForbidden();
        $this->getJson('/api/admin/groupes/filtres')->assertForbidden();
        $this->postJson('/api/admin/groupes/'.$groupe->id.'/archiver')->assertForbidden();
        $this->postJson('/api/admin/groupes/'.$groupe->id.'/synchroniser')->assertForbidden();
    }
}
