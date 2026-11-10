<?php

namespace Tests\Feature;

use App\Enums\EtatCompte;
use App\Mail\ActivationCompte;
use App\Models\AnneeUniversitaire;
use App\Models\CompteAutorise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SCRUM-62 : vérifier qu'une personne a le droit de s'inscrire (elle doit être
 * dans la liste des comptes autorisés) avant de créer son compte.
 * SCRUM-63 : si elle est éligible, le compte est créé en attente et l'e-mail
 * d'activation (lien signé 48 h vers la page Vue) est envoyé.
 *
 * Base de test : sqlite en mémoire (voir phpunit.xml), jamais la base de données réelle.
 */
class EligibiliteInscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/auth/register';

    private const CORPS = [
        'matricule' => 'LI2-2026-0457',
        'nom' => 'Ben Salah',
        'prenom' => 'Yassine',
        'email' => 'yassine.bensalah@univ.tn',
        'charte' => '1',
    ];

    public function test_sans_annee_active_le_refus_est_plateforme_fermee(): void
    {
        // Aucune année universitaire créée : la plateforme est fermée.
        $response = $this->postJson(self::URL, self::CORPS);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PLATEFORME_FERMEE')
            ->assertJsonPath('message', "La plateforme est fermée : aucune année universitaire n'est active, la création de compte est momentanément indisponible.")
            ->assertJsonStructure(['message', 'code', 'errors']);

        $this->assertSame(0, User::count());
        $this->assertSame(0, CompteAutorise::count());
    }

    public function test_hors_liste_des_comptes_autorises_le_refus_est_non_autorise(): void
    {
        $this->anneeActive();

        // Le matricule existe dans la liste, mais pas avec cet e-mail : le couple ne correspond pas.
        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'autre.adresse@univ.tn',
            'profil' => 'ETUDIANT',
        ]);

        $response = $this->postJson(self::URL, self::CORPS);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'NON_AUTORISE')
            ->assertJsonPath('message', "Ce matricule n'est pas autorisé à créer un compte : le couple matricule et adresse e-mail ne figure pas dans la liste des comptes autorisés.")
            ->assertJsonPath('errors.email.0', "Ce matricule n'est pas autorisé à créer un compte : le couple matricule et adresse e-mail ne figure pas dans la liste des comptes autorisés.");

        $this->assertSame(0, User::count());
    }

    public function test_compte_deja_cree_le_refus_est_deja_inscrit(): void
    {
        $this->anneeActive();

        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.tn',
            'profil' => 'ETUDIANT',
        ]);
        User::factory()->create(['matricule' => 'LI2-2026-0457']);

        $response = $this->postJson(self::URL, self::CORPS);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'DEJA_INSCRIT')
            ->assertJsonPath('message', "Un compte a déjà été créé pour ce matricule : l'inscription a déjà été effectuée.")
            ->assertJsonStructure(['message', 'code', 'errors']);

        $this->assertSame(1, User::count());
    }

    public function test_personne_eligible_cree_un_compte_en_attente_et_recoit_un_email(): void
    {
        Mail::fake();
        $this->anneeActive();

        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.tn',
            'profil' => 'ETUDIANT',
        ]);

        $response = $this->postJson(self::URL, self::CORPS);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'E-mail envoyé');

        // Compte créé mais pas encore actif.
        $user = User::sole();
        $this->assertSame(EtatCompte::EN_ATTENTE, $user->etat);
        $this->assertSame('LI2-2026-0457', $user->matricule);
        $this->assertSame('Ben Salah', $user->nom);
        $this->assertNull($user->password);

        // La liste des comptes autorisés est mise à jour dans la foulée.
        $autorise = CompteAutorise::sole();
        $this->assertSame(EtatCompte::EN_ATTENTE, $autorise->etat);
        $this->assertSame($user->id, $autorise->user_id);

        Mail::assertSent(ActivationCompte::class, fn (ActivationCompte $mail) => $mail->hasTo($user->email));
    }

    public function test_le_lien_d_activation_est_signe_valide_48h_et_pointe_vers_la_page_vue(): void
    {
        Mail::fake();
        $this->anneeActive();
        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.tn',
            'profil' => 'ETUDIANT',
        ]);

        $this->postJson(self::URL, self::CORPS)->assertStatus(201);

        $lien = null;
        Mail::assertSent(ActivationCompte::class, function (ActivationCompte $mail) use (&$lien) {
            $lien = $mail->lien;

            return true;
        });

        $user = User::sole();

        // Le lien de l'e-mail ouvre la page Vue /activation/{id}?expires=…&signature=…
        $this->assertMatchesRegularExpression(
            '#^'.preg_quote(config('app.frontend_url').'/activation/'.$user->id, '#').'\?expires=\d+&signature=#',
            $lien,
        );

        // La même signature reste valide sur l'URL backend équivalente…
        $lienBackend = Str::replace(
            config('app.frontend_url').'/activation',
            URL::to('/api/auth/activation'),
            $lien,
        );
        $this->assertTrue(URL::hasValidSignature(Request::create($lienBackend)));

        // …et la route backend signée redirige bien vers la page Vue.
        $requete = parse_url($lien, PHP_URL_QUERY);
        $this->get($lienBackend)->assertRedirect(
            config('app.frontend_url').'/activation/'.$user->id.'?'.$requete,
        );
    }

    public function test_un_tampon_de_signature_invalide_est_rejete(): void
    {
        $this->get(URL::to('/api/auth/activation/1?expires='.now()->addHour()->getTimestamp().'&signature=nimporte-quoi'))
            ->assertForbidden();
    }

    public function test_un_email_saisi_avec_des_majuscules_cree_le_compte_avec_l_adresse_de_la_liste(): void
    {
        Mail::fake();
        $this->anneeActive();
        $this->compteAutorise();

        $this->postJson(self::URL, array_merge(self::CORPS, ['email' => ' Yassine.BenSalah@UNIV.tn ']))
            ->assertStatus(201)
            ->assertJsonPath('email_envoye', true);

        // On garde l'adresse de la liste des comptes autorisés, pas celle tapée.
        $this->assertSame('yassine.bensalah@univ.tn', User::sole()->email);
        Mail::assertSent(ActivationCompte::class, fn (ActivationCompte $mail) => $mail->hasTo('yassine.bensalah@univ.tn'));
    }

    public function test_un_echec_d_envoi_de_l_email_ne_renvoie_pas_d_erreur_500(): void
    {
        $this->anneeActive();
        $this->compteAutorise();

        // Le serveur SMTP est indisponible au moment de l'envoi.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP indisponible'));

        $this->postJson(self::URL, self::CORPS)
            ->assertStatus(201)
            ->assertJsonPath('email_envoye', false);

        // Le compte est bien créé en attente : « Renvoyer le lien » (SCRUM-66) prendra le relais.
        $this->assertSame(EtatCompte::EN_ATTENTE, User::sole()->etat);
    }

    public function test_la_route_d_inscription_est_limitee_a_10_essais_par_minute(): void
    {
        $this->anneeActive();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::URL, self::CORPS)->assertStatus(422);
        }

        $this->postJson(self::URL, self::CORPS)->assertStatus(429);
    }

    public function test_formulaire_invalide_renvoie_422_avec_messages_francais(): void
    {
        $response = $this->postJson(self::URL, [
            'matricule' => '',
            'email' => 'pas-un-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.matricule.0', 'Le matricule est obligatoire.')
            ->assertJsonPath('errors.email.0', "Le format de l'adresse e-mail est invalide.")
            ->assertJsonPath('errors.charte.0', 'Vous devez accepter la charte pour créer un compte.');
    }

    /** Inscrit Yassine dans la liste des comptes autorisés. */
    private function compteAutorise(): void
    {
        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.tn',
            'profil' => 'ETUDIANT',
        ]);
    }

    /** Crée l'année universitaire active (une seule à la fois). */
    private function anneeActive(): void
    {
        AnneeUniversitaire::create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => true,
        ]);
    }
}
