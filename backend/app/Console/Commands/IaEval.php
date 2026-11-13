<?php

namespace App\Console\Commands;

use App\Enums\StatutEchangeIa;
use App\Exceptions\IAIndisponibleException;
use App\Models\AssistantIA;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\Ressource;
use App\Services\AssistantService;
use App\Services\IndexationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * SCRUM-180 : évaluation de l'Assistant IA sur le jeu de questions de référence (SCRUM-179).
 *
 * Contrairement à ia:poc, la commande passe par la vraie chaîne de l'application :
 * IndexationService (découpage du PDF) puis AssistantService (recherche plein texte,
 * Gemini avec secours Groq, citations). L'espace de test est créé dans une transaction
 * annulée à la fin : rien ne reste en base.
 *
 * Une réponse est « correcte » si :
 *   - question du cours : la page attendue fait partie des pages citées par le modèle ;
 *   - question hors sujet : l'assistant refuse (SANS_SOURCE).
 * La fidélité de chaque phrase à sa source n'est pas vérifiée : `reponse_attendue`
 * sert à la relecture humaine.
 *
 *   php artisan ia:eval C:/chemin/chap03.pdf
 *   php artisan ia:eval C:/chemin/chap03.pdf --limite=5 --sortie=   (5 questions, sans écrire le rapport)
 */
class IaEval extends Command
{
    protected $signature = 'ia:eval
        {fichier : Chemin du PDF de cours (chap03.pdf)}
        {--questions=tests/ia/questions.json : Jeu de questions (relatif au dossier backend)}
        {--pause=4 : Secondes entre deux questions (Gemini gratuit : 15 requêtes par minute)}
        {--limite=0 : Ne poser que les N premières questions (0 = toutes)}
        {--sortie=../docs/ia-evaluation.md : Rapport Markdown (relatif au dossier backend ; vide = pas de rapport)}';

    protected $description = "Évalue l'Assistant IA sur le jeu de questions de référence et publie les résultats";

    private int $nbFragments = 0;

    public function handle(IndexationService $indexation, AssistantService $assistant): int
    {
        $pdf = (string) $this->argument('fichier');
        if (! is_file($pdf)) {
            $this->error("Fichier introuvable : {$pdf}");

            return self::FAILURE;
        }

        $questions = $this->questions();
        if ($questions === null) {
            return self::FAILURE;
        }

        $chemin = 'ia-eval/'.Str::uuid().'/'.basename($pdf);
        Storage::put($chemin, (string) file_get_contents($pdf));

        DB::beginTransaction();
        try {
            $espace = $this->espaceDeTest($chemin, basename($pdf), $indexation);
            if ($espace === null) {
                return self::FAILURE;
            }

            $resultats = $this->evaluer($questions, $espace, $assistant);
        } finally {
            DB::rollBack();
            Storage::deleteDirectory(dirname($chemin));
        }

        $bilan = $this->bilan($resultats);
        $this->afficher($resultats, $bilan);

        $sortie = (string) $this->option('sortie');
        if ($sortie !== '') {
            $fichier = $this->chemin($sortie);
            file_put_contents($fichier, $this->rapport($resultats, $bilan, basename($pdf)));
            $this->info('Rapport écrit : '.realpath($fichier));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{question: string, document: ?string, page: ?int, hors_sujet: bool}>|null
     */
    private function questions(): ?array
    {
        $fichier = $this->chemin((string) $this->option('questions'));
        $donnees = is_file($fichier) ? json_decode((string) file_get_contents($fichier), true) : null;

        if (! is_array($donnees) || $donnees === []) {
            $this->error("Jeu de questions introuvable ou invalide : {$fichier}");

            return null;
        }

        $limite = max(0, (int) $this->option('limite'));

        return array_values($limite > 0 ? array_slice($donnees, 0, $limite) : $donnees);
    }

    private function espaceDeTest(string $chemin, string $nom, IndexationService $indexation): ?Espace
    {
        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Évaluation IA (temporaire)']);
        AssistantIA::create(['espace_id' => $espace->id, 'actif' => true]);
        $chapitre = Chapitre::create(['espace_id' => $espace->id, 'titre' => 'Évaluation', 'ordre' => 1]);

        $ressource = new Ressource([
            'chapitre_id' => $chapitre->id,
            'titre' => $nom,
            'chemin_fichier' => $chemin,
            'mime' => 'application/pdf',
        ]);
        $ressource->saveQuietly(); // pas de job en file d'attente : on indexe tout de suite

        $nb = $indexation->indexer($ressource);
        if ($nb === 0) {
            $this->error("Aucun texte extrait de {$nom} (PDF scanné ou illisible ?).");

            return null;
        }

        $this->nbFragments = $nb;
        $this->info("{$nom} indexé : {$nb} fragment(s).");

        return $espace;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return list<array<string, mixed>>
     */
    private function evaluer(array $questions, Espace $espace, AssistantService $assistant): array
    {
        $pause = max(0, (int) $this->option('pause'));
        $resultats = [];

        foreach ($questions as $i => $q) {
            if ($i > 0 && $pause > 0) {
                sleep($pause);
            }

            $ligne = [
                'n' => $i + 1,
                'question' => (string) $q['question'],
                'hors_sujet' => (bool) ($q['hors_sujet'] ?? false),
                'page_attendue' => isset($q['page']) ? (int) $q['page'] : null,
                'statut' => null,
                'pages' => [],
                'fournisseur' => null,
                'tokens_entree' => 0,
                'tokens_sortie' => 0,
                'duree_ms' => 0,
                'correct' => false,
            ];

            $debut = hrtime(true);
            try {
                $r = $assistant->poser($espace, $ligne['question']);
                $ligne['statut'] = $r->statut->value;
                $ligne['pages'] = array_values(array_unique(array_filter(array_column($r->sources, 'page'), fn ($p) => $p !== null)));
                sort($ligne['pages']);
                $ligne['fournisseur'] = $r->ia ? trim($r->ia->fournisseur.' · '.$r->ia->modele, ' ·') : null;
                $ligne['tokens_entree'] = $r->ia?->tokensEntree ?? 0;
                $ligne['tokens_sortie'] = $r->ia?->tokensSortie ?? 0;
                $ligne['duree_ms'] = $r->dureeMs;
            } catch (IAIndisponibleException) {
                $ligne['statut'] = StatutEchangeIa::ERREUR->value;
                $ligne['duree_ms'] = (int) round((hrtime(true) - $debut) / 1_000_000);
            } catch (Throwable $e) {
                $ligne['statut'] = StatutEchangeIa::ERREUR->value;
                $this->warn("Question {$ligne['n']} : ".$e->getMessage());
            }

            $ligne['correct'] = $ligne['hors_sujet']
                ? $ligne['statut'] === StatutEchangeIa::SANS_SOURCE->value
                : $ligne['statut'] === StatutEchangeIa::OK->value && in_array($ligne['page_attendue'], $ligne['pages'], true);

            $this->line(sprintf(
                '%2d/%d %s %-11s %s',
                $ligne['n'],
                count($questions),
                $ligne['correct'] ? '<info>✓</info>' : '<error>✗</error>',
                $ligne['statut'],
                Str::limit($ligne['question'], 60),
            ));

            $resultats[] = $ligne;
        }

        return $resultats;
    }

    /**
     * @param  list<array<string, mixed>>  $resultats
     * @return array<string, mixed>
     */
    private function bilan(array $resultats): array
    {
        $dans = array_filter($resultats, fn ($r) => ! $r['hors_sujet']);
        $hors = array_filter($resultats, fn ($r) => $r['hors_sujet']);
        $repondues = array_filter($resultats, fn ($r) => $r['fournisseur'] !== null);
        $durees = array_column($repondues, 'duree_ms');

        $fournisseurs = [];
        foreach ($repondues as $r) {
            $cle = explode(' · ', $r['fournisseur'])[0];
            $fournisseurs[$cle] = ($fournisseurs[$cle] ?? 0) + 1;
        }

        return [
            'total' => count($resultats),
            'dans_sujet' => count($dans),
            'sources_correctes' => count(array_filter($dans, fn ($r) => $r['correct'])),
            'hors_sujet' => count($hors),
            'refus_corrects' => count(array_filter($hors, fn ($r) => $r['correct'])),
            'erreurs' => count(array_filter($resultats, fn ($r) => $r['statut'] === StatutEchangeIa::ERREUR->value)),
            'sans_appel' => count($resultats) - count($repondues),
            'fournisseurs' => $fournisseurs,
            'duree_moyenne_s' => $durees === [] ? 0.0 : array_sum($durees) / count($durees) / 1000,
            'duree_max_s' => $durees === [] ? 0.0 : max($durees) / 1000,
            'tokens_entree' => array_sum(array_column($resultats, 'tokens_entree')),
            'tokens_sortie' => array_sum(array_column($resultats, 'tokens_sortie')),
        ];
    }

    private function pourcentage(int $n, int $sur): string
    {
        return $sur === 0 ? '-' : number_format(100 * $n / $sur, 0).' %';
    }

    /**
     * @param  list<array<string, mixed>>  $resultats
     * @param  array<string, mixed>  $b
     */
    private function afficher(array $resultats, array $b): void
    {
        $this->newLine();
        $this->table(
            ['N°', 'Question', 'Attendu', 'Statut', 'Pages citées', 'Correct', 'Fournisseur', 'Durée (s)'],
            array_map(fn ($r) => [
                $r['n'],
                Str::limit($r['question'], 40),
                $r['hors_sujet'] ? 'refus' : 'p. '.$r['page_attendue'],
                $r['statut'],
                $r['pages'] === [] ? '-' : implode(', ', $r['pages']),
                $r['correct'] ? 'oui' : 'non',
                $r['fournisseur'] ?? '-',
                number_format($r['duree_ms'] / 1000, 2, ',', ''),
            ], $resultats),
        );

        $this->line(sprintf('Sources correctes : %d / %d (%s)', $b['sources_correctes'], $b['dans_sujet'], $this->pourcentage($b['sources_correctes'], $b['dans_sujet'])));
        $this->line(sprintf('Refus corrects    : %d / %d', $b['refus_corrects'], $b['hors_sujet']));
        $this->line(sprintf('Erreurs (IA indisponible) : %d', $b['erreurs']));
        $this->line(sprintf('Durée : %.2f s en moyenne, %.2f s au maximum', $b['duree_moyenne_s'], $b['duree_max_s']));
        $this->line(sprintf('Tokens : %d en entrée, %d en sortie · Coût : 0 € (offres gratuites)', $b['tokens_entree'], $b['tokens_sortie']));
    }

    /**
     * @param  list<array<string, mixed>>  $resultats
     * @param  array<string, mixed>  $b
     */
    private function rapport(array $resultats, array $b, string $pdf): string
    {
        $fournisseurs = collect($b['fournisseurs'])->map(fn ($n, $f) => "{$f} × {$n}")->implode(', ') ?: 'aucun';
        $lignes = array_map(fn ($r) => sprintf(
            '| %d | %s | %s | %s | %s | %s | %s | %s | %s |',
            $r['n'],
            str_replace('|', '\|', $r['question']),
            $r['hors_sujet'] ? 'refus' : 'p. '.$r['page_attendue'],
            '`'.$r['statut'].'`',
            $r['pages'] === [] ? '-' : implode(', ', $r['pages']),
            $r['correct'] ? '✅' : '❌',
            $r['fournisseur'] ?? '- (pas d\'appel)',
            number_format($r['duree_ms'] / 1000, 1, ',', ''),
            $r['tokens_entree'] + $r['tokens_sortie'] ?: '-',
        ), $resultats);

        $date = now()->format('d/m/Y à H:i');

        return <<<MD
# Évaluation de l'Assistant IA

Résultats de `php artisan ia:eval` (SCRUM-180, US-43) sur le jeu de questions de référence `backend/tests/ia/questions.json` (SCRUM-179).

- **Date :** {$date}
- **Cours :** `{$pdf}`, découpé en {$this->nbFragments} fragment(s)
- **Chaîne testée :** celle de l'application — `IndexationService` (fragments d'environ 1 500 caractères) puis `AssistantService` (6 meilleurs extraits en recherche plein texte, Gemini avec secours Groq, citations `[n]`). L'espace de test est créé dans une transaction annulée à la fin.

## Bilan

| Indicateur | Résultat |
|---|---|
| **Sources correctes** (questions du cours) | **{$b['sources_correctes']} / {$b['dans_sujet']}** ({$this->pourcentage($b['sources_correctes'], $b['dans_sujet'])}) |
| **Refus corrects** (questions hors sujet) | **{$b['refus_corrects']} / {$b['hors_sujet']}** |
| Erreurs (Gemini et Groq indisponibles) | {$b['erreurs']} |
| Questions sans appel à l'IA (aucun extrait pertinent) | {$b['sans_appel']} |
| Fournisseurs | {$fournisseurs} |
| Durée de réponse | {$this->format($b['duree_moyenne_s'])} s en moyenne, {$this->format($b['duree_max_s'])} s au maximum |
| Tokens | {$b['tokens_entree']} en entrée, {$b['tokens_sortie']} en sortie |
| **Coût total** | **0 €** (offres gratuites de Gemini et de Groq) |

## Méthode

- **Question du cours :** correcte si la page attendue fait partie des pages **citées par le modèle** dans sa réponse (citations vérifiées par le backend).
- **Question hors sujet :** correcte si l'assistant refuse (`SANS_SOURCE`), soit parce qu'aucun extrait n'est trouvé (l'IA n'est pas appelée), soit parce que le modèle répond `JE_NE_SAIS_PAS`.
- **Limite :** la fidélité de chaque phrase à sa source n'est pas vérifiée automatiquement ; le champ `reponse_attendue` du jeu de questions sert à la relecture humaine. Les résultats d'un modèle d'IA peuvent varier d'une exécution à l'autre.

## Détail

| N° | Question | Attendu | Statut | Pages citées | Correct | Fournisseur | Durée (s) | Tokens |
|---|---|---|---|---|---|---|---|---|

MD
            .implode("\n", $lignes)."\n\n## Relancer l'évaluation\n\n```bash\ncd backend\nphp artisan ia:eval chemin/vers/chap03.pdf\n```\n\nLes clés `GEMINI_API_KEY` et `GROQ_API_KEY` doivent être renseignées dans `backend/.env`. Une pause de 4 s entre deux questions respecte la limite gratuite de Gemini (15 requêtes par minute).\n";
    }

    /** Chemin absolu tel quel, sinon relatif au dossier backend. */
    private function chemin(string $chemin): string
    {
        return str_starts_with($chemin, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $chemin)
            ? $chemin
            : base_path($chemin);
    }

    private function format(float $secondes): string
    {
        return number_format($secondes, 1, ',', '');
    }
}
