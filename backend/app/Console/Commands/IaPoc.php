<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Preuve de concept de l'Assistant IA (SCRUM-44, US-06).
 *
 * Lit un PDF de cours, le découpe en fragments, les indexe en recherche plein texte
 * PostgreSQL (dictionnaire french), envoie les 6 meilleurs extraits numérotés à Gemini
 * (secours : Groq) et affiche la réponse avec les pages citées.
 *
 * Prérequis : base PostgreSQL et, dans .env (lus par config/services.php, SCRUM-43) :
 *   GEMINI_API_KEY, GEMINI_MODEL, GROQ_API_KEY, GROQ_MODEL (défaut llama-3.3-70b-versatile).
 *
 * Exemples :
 *   php artisan ia:poc chemin/chap03.pdf "Quels sont les attributs d'un curseur explicite ?"
 *   php artisan ia:poc chemin/chap03.pdf "..." --details   (affiche les extraits retenus)
 *   php artisan ia:poc chemin/chap03.pdf "..." --groq      (force Groq pour tester le secours)
 */
class IaPoc extends Command
{
    protected $signature = 'ia:poc
        {fichier : Chemin du PDF de cours}
        {question : Question posée à l\'assistant}
        {--groq : Appeler directement Groq (test du secours)}
        {--details : Afficher les extraits envoyés à l\'IA}';

    protected $description = 'Preuve de concept : question sur un PDF de cours, réponse IA gratuite avec pages citées';

    private const TAILLE_FRAGMENT = 1500;   // caractères environ par fragment
    private const NB_EXTRAITS = 6;          // extraits envoyés à l'IA
    private const TIMEOUT = 30;             // secondes par appel IA
    private const REPONSE_INCONNUE = 'JE_NE_SAIS_PAS';

    public function handle(): int
    {
        $chemin = (string) $this->argument('fichier');
        $question = trim((string) $this->argument('question'));
        $nomDocument = basename($chemin);

        if (! is_file($chemin)) {
            $this->error("Fichier introuvable : {$chemin}");
            return self::FAILURE;
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('ia:poc a besoin de PostgreSQL (recherche plein texte). Vérifie DB_CONNECTION=pgsql dans .env.');
            return self::FAILURE;
        }

        $debut = microtime(true);

        // 1. Extraire le texte page par page, puis 2. découper en fragments.
        $pages = $this->extrairePages($chemin);
        $fragments = $this->decouper($pages);
        if ($fragments === []) {
            $this->error('Aucun texte extrait du PDF (document scanné ?).');
            return self::FAILURE;
        }
        $this->info(sprintf('%d page(s) lue(s), %d fragment(s) créé(s).', count($pages), count($fragments)));

        // 3. Indexer dans une table temporaire, puis 4. chercher les meilleurs extraits.
        $this->indexer($fragments);
        $extraits = $this->chercher($question);

        if ($extraits === []) {
            // Aucun extrait pertinent : on n'appelle pas l'IA (économise le quota gratuit).
            $this->warn('Statut : SANS_SOURCE (aucun extrait pertinent trouvé, l\'IA n\'est pas appelée).');
            return self::SUCCESS;
        }

        if ($this->option('details')) {
            $this->table(
                ['N°', 'Page', 'Score', 'Début de l\'extrait'],
                array_map(fn (array $e) => [
                    $e['numero'],
                    $e['page'],
                    round($e['score'], 4),
                    mb_strimwidth($e['contenu'], 0, 80, '…'),
                ], $extraits)
            );
        }

        // 5. Construire le prompt avec les extraits numérotés, puis 6. appeler l'IA.
        [$systeme, $message] = $this->construirePrompt($nomDocument, $extraits, $question);

        $debutIa = microtime(true);
        try {
            $reponse = $this->appelerIa($systeme, $message);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $dureeIa = microtime(true) - $debutIa;

        // 7. Lire les citations [n] et les relier au document et à la page.
        $texte = trim($reponse['texte']);
        $citees = $this->lireCitations($texte, $extraits);
        $statut = match (true) {
            str_contains($texte, self::REPONSE_INCONNUE) => 'SANS_SOURCE',
            $citees === [] => 'SANS_CITATION',
            default => 'OK',
        };

        $this->newLine();
        $this->line("<options=bold>Réponse ({$reponse['fournisseur']}) :</>");
        $this->line($texte);
        $this->newLine();

        if ($citees !== []) {
            $this->line('<options=bold>Sources citées :</>');
            foreach ($citees as $e) {
                $this->line(sprintf('  [%d] %s · p. %d', $e['numero'], $nomDocument, $e['page']));
            }
            $this->newLine();
        }

        // Mesures pour le tableau de SCRUM-45.
        $this->table(
            ['Statut', 'Fournisseur', 'Durée IA (s)', 'Durée totale (s)', 'Tokens entrée', 'Tokens sortie'],
            [[
                $statut,
                $reponse['fournisseur'],
                number_format($dureeIa, 2, ',', ''),
                number_format(microtime(true) - $debut, 2, ',', ''),
                $reponse['tokens_entree'],
                $reponse['tokens_sortie'],
            ]]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, string> texte nettoyé de chaque page, indexé à partir de 1
     */
    private function extrairePages(string $chemin): array
    {
        $pages = [];
        foreach ((new Parser())->parseFile($chemin)->getPages() as $index => $page) {
            $brut = $page->getText();
            $texte = trim(preg_replace('/\s+/u', ' ', $brut) ?? preg_replace('/\s+/', ' ', $brut));
            if ($texte !== '') {
                $pages[$index + 1] = $texte;
            }
        }

        return $pages;
    }

    /**
     * Coupe chaque page en morceaux d'environ TAILLE_FRAGMENT caractères, sans couper un mot.
     *
     * @param  array<int, string>  $pages
     * @return list<array{page: int, contenu: string}>
     */
    private function decouper(array $pages): array
    {
        $fragments = [];
        foreach ($pages as $numero => $texte) {
            while (mb_strlen($texte) > self::TAILLE_FRAGMENT) {
                $coupure = mb_strrpos(mb_substr($texte, 0, self::TAILLE_FRAGMENT), ' ');
                if ($coupure === false || $coupure < self::TAILLE_FRAGMENT / 2) {
                    $coupure = self::TAILLE_FRAGMENT;
                }
                $fragments[] = ['page' => $numero, 'contenu' => trim(mb_substr($texte, 0, $coupure))];
                $texte = trim(mb_substr($texte, $coupure));
            }
            if ($texte !== '') {
                $fragments[] = ['page' => $numero, 'contenu' => $texte];
            }
        }

        return $fragments;
    }

    /**
     * @param  list<array{page: int, contenu: string}>  $fragments
     */
    private function indexer(array $fragments): void
    {
        // Table temporaire : elle disparaît automatiquement à la fin de la commande.
        DB::statement('CREATE TEMPORARY TABLE ia_poc_fragments (
            id serial PRIMARY KEY,
            page integer NOT NULL,
            contenu text NOT NULL,
            recherche tsvector NOT NULL
        )');

        foreach ($fragments as $f) {
            DB::insert(
                "INSERT INTO ia_poc_fragments (page, contenu, recherche) VALUES (?, ?, to_tsvector('french', ?))",
                [$f['page'], $f['contenu'], $f['contenu']]
            );
        }
    }

    /**
     * Retourne les meilleurs extraits, numérotés de 1 à NB_EXTRAITS.
     *
     * websearch_to_tsquery exige TOUS les mots (ET) : une question en langage naturel
     * ne trouverait presque rien. On remplace donc les « & » par des « | » (OU)
     * et ts_rank classe les fragments qui contiennent le plus de mots de la question.
     *
     * @return list<array{numero: int, page: int, contenu: string, score: float}>
     */
    private function chercher(string $question): array
    {
        $lignes = DB::select(<<<'SQL'
            WITH q AS (
                SELECT NULLIF(replace(websearch_to_tsquery('french', ?)::text, ' & ', ' | '), '')::tsquery AS requete
            )
            SELECT f.page, f.contenu, ts_rank(f.recherche, q.requete) AS score
            FROM ia_poc_fragments f, q
            WHERE f.recherche @@ q.requete
            ORDER BY score DESC, f.id
            LIMIT ?
            SQL, [$question, self::NB_EXTRAITS]);

        $extraits = [];
        foreach ($lignes as $i => $ligne) {
            $extraits[] = [
                'numero' => $i + 1,
                'page' => (int) $ligne->page,
                'contenu' => $ligne->contenu,
                'score' => (float) $ligne->score,
            ];
        }

        return $extraits;
    }

    /**
     * @param  list<array{numero: int, page: int, contenu: string, score: float}>  $extraits
     * @return array{0: string, 1: string} [prompt système, message utilisateur]
     */
    private function construirePrompt(string $nomDocument, array $extraits, string $question): array
    {
        $systeme = "Tu es l'assistant du cours. Réponds en français uniquement à partir des extraits numérotés fournis. "
            .'Après chaque information, indique le numéro de l\'extrait entre crochets, par exemple [2]. '
            .'Si la réponse n\'est pas dans les extraits, réponds exactement : '.self::REPONSE_INCONNUE;

        $blocs = array_map(
            fn (array $e) => sprintf('[%d] (%s, p. %d) %s', $e['numero'], $nomDocument, $e['page'], $e['contenu']),
            $extraits
        );

        $message = "Extraits du cours :\n\n".implode("\n\n", $blocs)."\n\nQuestion : ".$question;

        return [$systeme, $message];
    }

    /**
     * Appelle Gemini, et bascule vers Groq si Gemini échoue (429 quota, clé invalide, réseau…).
     *
     * @return array{fournisseur: string, texte: string, tokens_entree: int, tokens_sortie: int}
     */
    private function appelerIa(string $systeme, string $message): array
    {
        if (! $this->option('groq')) {
            try {
                return $this->appelerGemini($systeme, $message);
            } catch (RuntimeException $e) {
                $this->warn($e->getMessage().' → bascule vers Groq.');
            }
        }

        return $this->appelerGroq($systeme, $message);
    }

    /**
     * @return array{fournisseur: string, texte: string, tokens_entree: int, tokens_sortie: int}
     */
    private function appelerGemini(string $systeme, string $message): array
    {
        $cle = config('services.gemini.key');
        $modele = config('services.gemini.model');
        if (empty($cle) || empty($modele)) {
            throw new RuntimeException('Gemini non configuré (GEMINI_API_KEY ou GEMINI_MODEL vide)');
        }

        try {
            $r = Http::withHeaders(['x-goog-api-key' => $cle])
                ->acceptJson()
                ->timeout(self::TIMEOUT)
                ->post(config('services.gemini.url')."/models/{$modele}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $systeme]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $message]]]],
                    'generationConfig' => ['temperature' => 0.2],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Gemini injoignable (réseau ou délai de '.self::TIMEOUT.' s dépassé)', 0, $e);
        }

        if ($r->failed()) {
            $raison = $r->status() === 429 ? 'quota gratuit dépassé' : ($r->json('error.message') ?? 'erreur');
            throw new RuntimeException("Gemini a répondu HTTP {$r->status()} ({$raison})");
        }

        $texte = collect($r->json('candidates.0.content.parts', []))->pluck('text')->implode('');
        if (trim($texte) === '') {
            $fin = $r->json('candidates.0.finishReason', 'inconnue');
            throw new RuntimeException("Gemini a renvoyé une réponse vide (finishReason : {$fin})");
        }

        return [
            'fournisseur' => "gemini · {$modele}",
            'texte' => $texte,
            'tokens_entree' => (int) $r->json('usageMetadata.promptTokenCount', 0),
            // Les modèles « thinking » comptent aussi leurs tokens de réflexion dans le quota.
            'tokens_sortie' => (int) $r->json('usageMetadata.candidatesTokenCount', 0)
                + (int) $r->json('usageMetadata.thoughtsTokenCount', 0),
        ];
    }

    /**
     * @return array{fournisseur: string, texte: string, tokens_entree: int, tokens_sortie: int}
     */
    private function appelerGroq(string $systeme, string $message): array
    {
        $cle = config('services.groq.key');
        $modele = config('services.groq.model');
        if (empty($cle)) {
            throw new RuntimeException('Groq non configuré (GROQ_API_KEY vide) : aucune IA disponible.');
        }

        try {
            $r = Http::withToken($cle)
                ->acceptJson()
                ->timeout(self::TIMEOUT)
                ->post(config('services.groq.url').'/chat/completions', [
                    'model' => $modele,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systeme],
                        ['role' => 'user', 'content' => $message],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Groq injoignable : aucune IA disponible.', 0, $e);
        }

        if ($r->failed()) {
            $raison = $r->status() === 429 ? 'quota gratuit dépassé' : ($r->json('error.message') ?? 'erreur');
            throw new RuntimeException("Groq a répondu HTTP {$r->status()} ({$raison}) : aucune IA disponible.");
        }

        return [
            'fournisseur' => "groq · {$modele}",
            'texte' => (string) $r->json('choices.0.message.content', ''),
            'tokens_entree' => (int) $r->json('usage.prompt_tokens', 0),
            'tokens_sortie' => (int) $r->json('usage.completion_tokens', 0),
        ];
    }

    /**
     * Extrait les numéros cités ([2], [1][3] ou [1, 3]) et garde ceux qui existent, sans doublon.
     *
     * @param  list<array{numero: int, page: int, contenu: string, score: float}>  $extraits
     * @return list<array{numero: int, page: int, contenu: string, score: float}>
     */
    private function lireCitations(string $texte, array $extraits): array
    {
        preg_match_all('/\[(\d+(?:\s*,\s*\d+)*)\]/', $texte, $m);

        $numeros = [];
        foreach ($m[1] as $groupe) {
            foreach (preg_split('/\s*,\s*/', $groupe) as $n) {
                $n = (int) $n;
                if ($n >= 1 && $n <= count($extraits)) {
                    $numeros[$n] = true;
                }
            }
        }
        ksort($numeros);

        return array_map(fn (int $n) => $extraits[$n - 1], array_keys($numeros));
    }
}
