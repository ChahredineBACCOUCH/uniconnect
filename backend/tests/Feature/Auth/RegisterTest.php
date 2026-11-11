<?php

namespace Tests\Feature\Auth;

use App\Enums\EtatCompte;
use App\Mail\ActivationCompte;
use App\Models\AnneeUniversitaire;
use App\Models\CompteAutorise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * SCRUM-65 : cas de refus et cas nominal de la création de compte
 * (POST /api/auth/register).
 *
 * Le compte n'est créé que si la personne figure sur la liste des comptes
 * autorisés de l'année universitaire active. Dans le cas contraire, l'API
 * refuse (422) et aucun e-mail n'est envoyé.
 *
 * Base de test : base jetable (sqlite en mémoire en local, PostgreSQL en CI —
 * voir phpunit.xml et .github/workflows/backend.yml), jamais la base réelle.
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/auth/register';

    /** Corps envoyé par l'écran d'inscription (maquette E02). */
    private const CORPS = [
        'matricule' => 'LI2-2026-0457',
        'nom' => 'Ben Salah',
        'prenom' => 'Yassine',
        'email' => 'yassine.bensalah@univ.tn',
        'charte' => true,
    ];

    public function test_un_matricule_absent_de_la_liste_des_comptes_autorises_est_refuse(): void
    {
        Mail::fake();
        $this->anneeActive();

        // La liste des comptes autorisés existe, mais pas avec ce matricule.
        $this->compteAutorise([
            'matricule' => 'LI2-2026-0999',
            'email' => 'autre.personne@univ.tn',
        ]);

        $this->postJson(self::URL, self::CORPS)
            ->assertStatus(422)
            ->assertJsonPath('code', 'NON_AUTORISE')
            ->assertJsonPath(
                'errors.email.0',
                "Ce matricule n'est pas autorisé à créer un compte : le couple matricule et adresse e-mail ne figure pas dans la liste des comptes autorisés.",
            );

        // Aucun compte créé et aucun e-mail envoyé.
        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_un_compte_deja_cree_pour_ce_matricule_est_refuse(): void
    {
        Mail::fake();
        $this->anneeActive();
        $this->compteAutorise();

        // Le compte existe déjà : une nouvelle inscription n'a pas lieu d'être.
        User::factory()->create(['matricule' => 'LI2-2026-0457']);

        $this->postJson(self::URL, self::CORPS)
            ->assertStatus(422)
            ->assertJsonPath('code', 'DEJA_INSCRIT')
            ->assertJsonPath(
                'message',
                "Un compte a déjà été créé pour ce matricule : l'inscription a déjà été effectuée.",
            );

        // Le compte existant n'est pas touché et aucun e-mail n'est envoyé.
        $this->assertSame(1, User::count());
        Mail::assertNothingSent();
    }

    public function test_sans_annee_universitaire_active_le_refus_est_plateforme_fermee(): void
    {
        Mail::fake();

        // L'année universitaire est désactivée : la plateforme est fermée.
        $annee = $this->anneeActive();
        $annee->update(['active' => false]);

        $this->postJson(self::URL, self::CORPS)
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLATEFORME_FERMEE')
            ->assertJsonPath(
                'message',
                "La plateforme est fermée : aucune année universitaire n'est active, la création de compte est momentanément indisponible.",
            );

        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_la_charte_doit_etre_acceptee_pour_creer_un_compte(): void
    {
        Mail::fake();
        $this->anneeActive();
        $this->compteAutorise();

        // La case « J'accepte la charte d'utilisation » n'est pas cochée.
        $this->postJson(self::URL, array_merge(self::CORPS, ['charte' => false]))
            ->assertStatus(422)
            ->assertJsonPath('errors.charte.0', 'Vous devez accepter la charte pour créer un compte.');

        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_une_personne_autorisee_cree_un_compte_en_attente_et_recoit_un_email(): void
    {
        Mail::fake();
        $this->anneeActive();
        $this->compteAutorise();

        $this->postJson(self::URL, self::CORPS)
            ->assertStatus(201)
            ->assertJsonPath('message', 'E-mail envoyé')
            ->assertJsonPath('email_envoye', true);

        // Compte créé mais pas encore actif : le mot de passe sera choisi via le lien.
        $user = User::sole();
        $this->assertSame(EtatCompte::EN_ATTENTE, $user->etat);
        $this->assertSame('LI2-2026-0457', $user->matricule);
        $this->assertSame('yassine.bensalah@univ.tn', $user->email);
        $this->assertNull($user->password);

        Mail::assertSent(ActivationCompte::class, fn (ActivationCompte $mail) => $mail->hasTo($user->email));
    }

    /** Inscrit Yassine dans la liste des comptes autorisés. */
    private function compteAutorise(array $attributs = []): CompteAutorise
    {
        return CompteAutorise::create(array_merge([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.tn',
            'profil' => 'ETUDIANT',
        ], $attributs));
    }

    /** Crée l'année universitaire active (une seule à la fois). */
    private function anneeActive(): AnneeUniversitaire
    {
        return AnneeUniversitaire::create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => true,
        ]);
    }
}
