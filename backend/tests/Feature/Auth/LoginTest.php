<?php

namespace Tests\Feature\Auth;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SCRUM-69 : connexion (jeton Sanctum), blocage après 5 échecs, déconnexion
 * et profil de l'utilisateur connecté.
 *
 * Base de test : base jetable (sqlite en mémoire en local, PostgreSQL en CI —
 * voir phpunit.xml et .github/workflows/backend.yml), jamais la base réelle.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const MOT_DE_PASSE = 'MotDePasse1';

    /** Classe créée à la demande (une seule fois par test). */
    private ?Classe $classe = null;

    public function test_une_connexion_reussie_renvoie_un_jeton_et_l_utilisateur(): void
    {
        $compte = $this->compte(['tentatives_echouees' => 2]);

        $reponse = $this->postJson('/api/auth/login', [
            'email' => $compte->email,
            'password' => self::MOT_DE_PASSE,
        ])->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', $compte->email)
            ->assertJsonPath('user.profil', 'ETUDIANT');

        $this->assertIsString($reponse->json('token'));

        // Le compteur d'échecs est remis à zéro et la connexion est datée.
        $compte->refresh();
        $this->assertSame(0, $compte->tentatives_echouees);
        $this->assertNotNull($compte->derniere_connexion);

        // Le jeton est utilisable pour les routes protégées.
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withHeader('Authorization', 'Bearer '.$reponse->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $compte->email);
    }

    public function test_un_mot_de_passe_faux_est_refuse_et_compte(): void
    {
        $compte = $this->compte();

        $this->postJson('/api/auth/login', [
            'email' => $compte->email,
            'password' => 'MauvaisMotDePasse1',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Identifiants incorrects.')
            ->assertJsonPath('tentatives_restantes', 4);

        $this->assertSame(1, $compte->refresh()->tentatives_echouees);
        $this->assertNull($compte->bloque_jusqua);
    }

    public function test_cinq_echecs_bloquent_le_compte_pendant_quinze_minutes(): void
    {
        $compte = $this->compte();

        for ($essai = 1; $essai <= 4; $essai++) {
            $this->postJson('/api/auth/login', [
                'email' => $compte->email,
                'password' => 'MauvaisMotDePasse1',
            ])->assertStatus(422);
        }

        // Le cinquième essai déclenche le blocage.
        $this->postJson('/api/auth/login', [
            'email' => $compte->email,
            'password' => 'MauvaisMotDePasse1',
        ])
            ->assertStatus(423)
            ->assertJsonPath(
                'message',
                'Trop de tentatives : ce compte est bloqué pendant 15 minutes.',
            );

        $compte->refresh();
        $this->assertNotNull($compte->bloque_jusqua);
        $this->assertTrue($compte->bloque_jusqua->isFuture());
        $this->assertSame(0, $compte->tentatives_echouees);

        // Tant que le compte est bloqué, même le bon mot de passe est refusé.
        $this->postJson('/api/auth/login', [
            'email' => $compte->email,
            'password' => self::MOT_DE_PASSE,
        ])->assertStatus(423);
    }

    public function test_le_compte_se_debloque_apres_quinze_minutes(): void
    {
        $compte = $this->compte();

        // Cinq mots de passe faux : le compte est bloqué pour quinze minutes.
        for ($essai = 1; $essai <= 5; $essai++) {
            $this->postJson('/api/auth/login', [
                'email' => $compte->email,
                'password' => 'MauvaisMotDePasse1',
            ]);
        }

        // Immédiatement après, même le bon mot de passe est refusé.
        $this->postJson('/api/auth/login', [
            'email' => $compte->email,
            'password' => self::MOT_DE_PASSE,
        ])->assertStatus(423);

        // Seize minutes plus tard, le blocage a expiré : la connexion réussit.
        $this->travelTo(now()->addMinutes(16));

        $this->postJson('/api/auth/login', [
            'email' => $compte->email,
            'password' => self::MOT_DE_PASSE,
        ])->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', $compte->email);

        $compte->refresh();
        $this->assertNull($compte->bloque_jusqua);
        $this->assertSame(0, $compte->tentatives_echouees);
    }

    public function test_un_compte_bloque_renvoie_l_heure_de_deblocage(): void
    {
        $compte = $this->compte(['bloque_jusqua' => now()->addMinutes(10)]);

        $this->postJson('/api/auth/login', [
            'email' => $compte->email,
            'password' => self::MOT_DE_PASSE,
        ])
            ->assertStatus(423)
            ->assertJsonPath(
                'message',
                'Compte bloqué jusqu\'à '.now()->addMinutes(10)->format('H:i').'.',
            );
    }

    public function test_un_compte_desactive_ou_suspendu_ne_peut_plus_se_connecter(): void
    {
        $desactive = $this->compte([
            'email' => 'compte.desactive@univ.tn',
            'etat' => EtatCompte::DESACTIVE,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $desactive->email,
            'password' => self::MOT_DE_PASSE,
        ])
            ->assertStatus(403)
            ->assertJsonPath(
                'message',
                "Ce compte est désactivé : contactez l'administration.",
            );

        $suspendu = $this->compte([
            'email' => 'compte.suspendu@univ.tn',
            'etat' => EtatCompte::SUSPENDU,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $suspendu->email,
            'password' => self::MOT_DE_PASSE,
        ])
            ->assertStatus(403)
            ->assertJsonPath(
                'message',
                "Ce compte est suspendu : contactez l'administration.",
            );
    }

    public function test_l_e_mail_est_reconnu_sans_tenir_compte_de_la_casse(): void
    {
        $compte = $this->compte();

        $this->postJson('/api/auth/login', [
            'email' => ' '.mb_strtoupper($compte->email).' ',
            'password' => self::MOT_DE_PASSE,
        ])->assertOk()
            ->assertJsonPath('user.id', $compte->id);
    }

    public function test_un_compte_inconnu_renvoie_le_meme_message_qu_un_mot_de_passe_faux(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'personne.inconnue@univ.tn',
            'password' => self::MOT_DE_PASSE,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Identifiants incorrects.');
    }

    public function test_la_deconnexion_supprime_le_jeton(): void
    {
        $compte = $this->compte();

        $token = $this->postJson('/api/auth/login', [
            'email' => $compte->email,
            'password' => self::MOT_DE_PASSE,
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        // Le jeton ne fonctionne plus. (Les gardes sont oubliés entre les deux
        // requêtes : sans cela, la garde garde l'utilisateur en mémoire.)
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_le_profil_renvoie_l_utilisateur_et_ses_droits(): void
    {
        $compte = $this->compte(['profil' => 'ADMINISTRATION']);
        $compte->accorderDroit(Droit::GERER_UTILISATEURS);

        $token = $compte->createToken('web')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $compte->email)
            ->assertJsonPath('profil', 'ADMINISTRATION')
            ->assertJsonPath('droits', ['GERER_UTILISATEURS'])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('tentatives_echouees');
    }

    public function test_sans_jeton_le_profil_est_refuse(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    /** Compte actif avec un mot de passe connu. */
    private function compte(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'yassine.bensalah@univ.tn',
            'password' => Hash::make(self::MOT_DE_PASSE),
            'etat' => EtatCompte::ACTIF,
            'tentatives_echouees' => 0,
            'bloque_jusqua' => null,
            // Un étudiant actif doit être rattaché à une classe (contrainte de la base).
            'classe_id' => $this->classe()->id,
        ], $attributs));
    }

    /** Classe de L2 Informatique A (créée une seule fois par test). */
    private function classe(): Classe
    {
        if ($this->classe !== null) {
            return $this->classe;
        }

        $annee = AnneeUniversitaire::create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => true,
        ]);

        $departement = Departement::create(['code' => 'INFO', 'libelle' => 'Informatique']);

        $filiere = Filiere::create([
            'code' => 'GL',
            'libelle' => 'Génie Logiciel',
            'departement_id' => $departement->id,
        ]);

        $niveau = Niveau::create(['code' => 'L2', 'libelle' => 'Licence 2']);

        return $this->classe = Classe::create([
            'code' => 'L2-INFO-A',
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ]);
    }
}
