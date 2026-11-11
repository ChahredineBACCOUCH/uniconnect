<?php

namespace Tests\Feature\IA;

use App\Exceptions\IAIndisponibleException;
use App\IA\ClientIA;
use App\IA\ClientIAAvecSecours;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SCRUM-100 : Gemini, Groq et la bascule de secours, avec Http::fake (aucun appel réel, aucune clé).
 */
class ClientIATest extends TestCase
{
    private const GEMINI = 'https://generativelanguage.googleapis.com/*';

    private const GROQ = 'https://api.groq.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'cle-gemini-de-test',
            'services.gemini.model' => 'gemini-de-test',
            'services.groq.key' => 'cle-groq-de-test',
            'services.groq.model' => 'groq-de-test',
        ]);
    }

    private function reponseGemini(string $texte): array
    {
        return [
            'candidates' => [['content' => ['parts' => [['text' => $texte]]]]],
            'usageMetadata' => ['promptTokenCount' => 2097, 'candidatesTokenCount' => 71, 'thoughtsTokenCount' => 9],
        ];
    }

    private function reponseGroq(string $texte): array
    {
        return [
            'choices' => [['message' => ['content' => $texte]]],
            'usage' => ['prompt_tokens' => 2063, 'completion_tokens' => 292],
        ];
    }

    public function test_client_ia_est_gemini_puis_groq(): void
    {
        $this->assertInstanceOf(ClientIAAvecSecours::class, app(ClientIA::class));
    }

    public function test_gemini_repond_sans_appeler_groq(): void
    {
        Http::fake([self::GEMINI => Http::response($this->reponseGemini('Les attributs sont %FOUND [1].'))]);

        $reponse = app(ClientIA::class)->repondre('Consigne', 'Question');

        $this->assertSame('gemini', $reponse->fournisseur);
        $this->assertSame('Les attributs sont %FOUND [1].', $reponse->texte);
        $this->assertSame(2097, $reponse->tokensEntree);
        $this->assertSame(80, $reponse->tokensSortie); // 71 + 9 tokens de réflexion
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->hasHeader('x-goog-api-key', 'cle-gemini-de-test')
            && str_contains($r->url(), 'models/gemini-de-test:generateContent')
            && $r['systemInstruction']['parts'][0]['text'] === 'Consigne');
    }

    public function test_gemini_429_bascule_vers_groq(): void
    {
        Http::fake([
            self::GEMINI => Http::response(['error' => ['message' => 'Quota exceeded']], 429),
            self::GROQ => Http::response($this->reponseGroq('Réponse de secours [2].')),
        ]);

        $reponse = app(ClientIA::class)->repondre('Consigne', 'Question');

        $this->assertSame('groq', $reponse->fournisseur);
        $this->assertSame('groq-de-test', $reponse->modele);
        $this->assertSame(292, $reponse->tokensSortie);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.groq.com')
            && $r->hasHeader('Authorization', 'Bearer cle-groq-de-test')
            && $r['messages'][0] === ['role' => 'system', 'content' => 'Consigne']);
    }

    public function test_cle_gemini_absente_bascule_vers_groq(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake([self::GROQ => Http::response($this->reponseGroq('OK [1]'))]);

        $this->assertSame('groq', app(ClientIA::class)->repondre('s', 'm')->fournisseur);
        Http::assertSentCount(1);
    }

    public function test_les_deux_en_echec_leve_ia_indisponible(): void
    {
        Http::fake([
            self::GEMINI => Http::response([], 503),
            self::GROQ => Http::response([], 500),
        ]);

        $this->expectException(IAIndisponibleException::class);

        app(ClientIA::class)->repondre('s', 'm');
    }

    public function test_reponse_vide_de_gemini_bascule_vers_groq(): void
    {
        Http::fake([
            self::GEMINI => Http::response(['candidates' => [['content' => ['parts' => []], 'finishReason' => 'SAFETY']]]),
            self::GROQ => Http::response($this->reponseGroq('Réponse [1]')),
        ]);

        $this->assertSame('groq', app(ClientIA::class)->repondre('s', 'm')->fournisseur);
    }
}
