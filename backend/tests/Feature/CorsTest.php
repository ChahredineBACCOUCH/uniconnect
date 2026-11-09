<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * SCRUM-33 : seul le front de l'équipe (FRONTEND_URL et aperçus Vercel du compte)
 * peut appeler l'API depuis un navigateur.
 */
class CorsTest extends TestCase
{
    private const PRODUCTION = 'https://uniconnect-six-lake.vercel.app';

    private const APERCU = 'https://uniconnect-git-fix-sc-0a99d7-chahredinebaccouche-2264s-projects.vercel.app';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cors.allowed_origins' => [self::PRODUCTION]]);
    }

    private function preflight(string $origine)
    {
        return $this->call('OPTIONS', '/api/health', server: [
            'HTTP_ORIGIN' => $origine,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ]);
    }

    public function test_le_front_de_production_est_autorise(): void
    {
        $this->preflight(self::PRODUCTION)
            ->assertHeader('Access-Control-Allow-Origin', self::PRODUCTION);
    }

    public function test_les_apercus_vercel_de_l_equipe_sont_autorises(): void
    {
        $this->preflight(self::APERCU)
            ->assertHeader('Access-Control-Allow-Origin', self::APERCU);
    }

    public function test_les_autres_origines_sont_bloquees(): void
    {
        foreach ([
            'https://site-pirate.example',
            'https://uniconnect-nimportequi.vercel.app',
            'http://localhost:5173',
        ] as $origine) {
            $this->preflight($origine)->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    public function test_pas_de_cookies_on_utilise_des_jetons_bearer(): void
    {
        $this->preflight(self::PRODUCTION)
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_le_env_example_autorise_le_front_local(): void
    {
        $this->assertMatchesRegularExpression(
            '/^FRONTEND_URL=http:\/\/localhost:5173$/m',
            file_get_contents(base_path('.env.example')),
        );
    }
}
