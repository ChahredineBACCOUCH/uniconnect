<?php

namespace Tests\Unit;

use App\Models\AnneeUniversitaire;
use App\Models\CompteAutorise;
use App\Services\EligibiliteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SCRUM-62 : l'adresse e-mail saisie ne doit pas dépendre de la casse ni des
 * espaces en trop (copier-coller depuis un mail, majuscule automatique sur mobile).
 */
class EligibiliteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AnneeUniversitaire::create([
            'libelle' => '2026/2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'active' => true,
        ]);

        CompteAutorise::create([
            'matricule' => 'LI2-2026-0457',
            'nom' => 'Ben Salah',
            'prenom' => 'Yassine',
            'email' => 'yassine.bensalah@univ.tn',
            'profil' => 'ETUDIANT',
        ]);
    }

    public function test_l_e_mail_est_compare_sans_tenir_compte_de_la_casse(): void
    {
        $this->assertNull(
            app(EligibiliteService::class)->verifier('LI2-2026-0457', 'Yassine.BenSalah@UNIV.tn'),
        );
    }

    public function test_les_espaces_autour_du_matricule_et_de_l_e_mail_sont_ignores(): void
    {
        $this->assertNull(
            app(EligibiliteService::class)->verifier('  LI2-2026-0457 ', ' yassine.bensalah@univ.tn  '),
        );
    }

    public function test_une_autre_adresse_reste_refusee(): void
    {
        $this->assertSame(
            EligibiliteService::NON_AUTORISE,
            app(EligibiliteService::class)->verifier('LI2-2026-0457', 'autre@univ.tn'),
        );
    }
}
