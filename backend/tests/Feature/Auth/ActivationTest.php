<?php

namespace Tests\Feature\Auth;

use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Mail\ActivationCompte;
use App\Models\AnneeUniversitaire;
use App\Models\Classe;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * SCRUM-66 : activation du compte (choix du mot de passe) et renvoi du lien.
 *
 * Base de test : base jetable (sqlite en mémoire en local, PostgreSQL en CI —
 * voir phpunit.xml et .github/workflows/backend.yml), jamais la base réelle.
 */
class ActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_lien_valide_active_le_compte_et_renvoie_un_jeton(): void
    {
        $compte = $this->compteEnAttente();

        $this->postJson($this->urlActivation($compte), [
            'password' => 'MotDePasse1',
            'password_confirmation' => 'MotDePasse1',
        ])
            ->assertStatus(200)
            ->assertJsonPath('user.email', $compte->email)
            ->assertJsonStructure(['token', 'user']);

        $compte->refresh();

        // Le compte est actif, son e-mail vérifié et son mot de passe enregistré.
        $this->assertSame(EtatCompte::ACTIF, $compte->etat);
        $this->assertNotNull($compte->email_verified_at);
        $this->assertTrue(Hash::check('MotDePasse1', $compte->password));

        // Un jeton Sanctum est renvoyé pour connecter la personne directement.
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Droits par défaut du profil (maquette A04) : créer un groupe privé (SCRUM-89).
        $this->assertTrue($compte->possedeDroit(Droit::CREER_GROUPE_PRIVE));
        $this->assertSame(1, $compte->droits()->count());
    }

    public function test_un_lien_expire_est_refuse(): void
    {
        $compte = $this->compteEnAttente();

        // Le lien est reçu à l'inscription…
        $url = $this->urlActivation($compte);

        // …et valable 48 h : plus de 49 h après, il ne l'est plus.
        $this->travelTo(now()->addHours(49));

        $this->postJson($url, [
            'password' => 'MotDePasse1',
            'password_confirmation' => 'MotDePasse1',
        ])->assertForbidden();

        $compte->refresh();
        $this->assertSame(EtatCompte::EN_ATTENTE, $compte->etat);
        $this->assertNull($compte->password);
    }

    public function test_un_mot_de_passe_trop_faible_est_refuse(): void
    {
        $compte = $this->compteEnAttente();

        // Ni majuscule ni chiffre, et moins de 8 caractères.
        $this->postJson($this->urlActivation($compte), [
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $compte->refresh();
        $this->assertSame(EtatCompte::EN_ATTENTE, $compte->etat);
        $this->assertNull($compte->password);
    }

    public function test_un_compte_deja_active_ne_peut_plus_etre_active(): void
    {
        $compte = $this->compteEnAttente(['etat' => EtatCompte::ACTIF]);

        // Le lien ne sert plus à changer le mot de passe d'un compte actif.
        $this->postJson($this->urlActivation($compte), [
            'password' => 'NouveauMotDePasse1',
            'password_confirmation' => 'NouveauMotDePasse1',
        ])->assertForbidden();
    }

    public function test_un_etudiant_sans_classe_ne_peut_pas_etre_active(): void
    {
        // Un étudiant actif doit être rattaché à une classe (contrainte de la base).
        $compte = $this->compteEnAttente(['classe_id' => null]);

        $this->postJson($this->urlActivation($compte), [
            'password' => 'MotDePasse1',
            'password_confirmation' => 'MotDePasse1',
        ])->assertStatus(422);

        $compte->refresh();
        $this->assertSame(EtatCompte::EN_ATTENTE, $compte->etat);
    }

    public function test_le_renvoi_du_lien_envoie_un_email_si_le_compte_est_en_attente(): void
    {
        Mail::fake();
        $compte = $this->compteEnAttente();

        $this->postJson('/api/auth/activate/renvoyer', ['email' => $compte->email])
            ->assertStatus(202);

        Mail::assertSent(ActivationCompte::class, fn (ActivationCompte $mail) => $mail->hasTo($compte->email));
    }

    public function test_le_renvoi_du_lien_n_envoie_rien_si_le_compte_est_deja_actif(): void
    {
        Mail::fake();
        $compte = $this->compteEnAttente(['etat' => EtatCompte::ACTIF]);

        $this->postJson('/api/auth/activate/renvoyer', ['email' => $compte->email])
            ->assertStatus(202);

        Mail::assertNothingSent();
    }

    /** Compte créé par l'inscription : en attente d'activation, sans mot de passe. */
    private function compteEnAttente(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'matricule' => 'LI2-2026-0457',
            'email' => 'yassine.bensalah@univ.tn',
            'etat' => EtatCompte::EN_ATTENTE,
            'password' => null,
            'email_verified_at' => null,
            'classe_id' => $this->classe()->id,
        ], $attributs));
    }

    /** Classe de L2 Informatique A : un étudiant actif doit être rattaché à une classe. */
    private function classe(): Classe
    {
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

        return Classe::create([
            'code' => 'L2-INFO-A',
            'libelle' => 'L2 Informatique A',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'annee_id' => $annee->id,
        ]);
    }

    /**
     * URL d'activation : le lien signé reçu par e-mail (route GET) est renvoyé
     * tel quel sur la route POST, avec les paramètres expires et signature.
     */
    private function urlActivation(User $compte): string
    {
        $lien = URL::temporarySignedRoute('activation', now()->addHours(48), ['user' => $compte->id]);

        return '/api/auth/activate/'.$compte->id.'?'.parse_url($lien, PHP_URL_QUERY);
    }
}
