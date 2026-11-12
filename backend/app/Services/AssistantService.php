<?php

namespace App\Services;

use App\Enums\StatutEchangeIa;
use App\IA\ClientIA;
use App\IA\ResultatAssistant;
use App\Models\Espace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cœur de l'Assistant IA (SCRUM-100) : trouve les meilleurs passages des ressources
 * de l'espace, les envoie à l'IA gratuite et relie les citations [n] aux fichiers et pages.
 * L'enregistrement de l'échange et la route API sont dans SCRUM-102.
 */
class AssistantService
{
    public const NB_EXTRAITS = 6;

    public const REPONSE_INCONNUE = 'JE_NE_SAIS_PAS';

    public const PROMPT_SYSTEME = "Tu es l'assistant du cours. Réponds uniquement à partir des extraits numérotés fournis, en français. "
        .'Après chaque information, cite le numéro de l\'extrait entre crochets, par exemple [2]. '
        .'Si la réponse n\'y est pas, réponds exactement '.self::REPONSE_INCONNUE.'.';

    public function __construct(private ClientIA $ia) {}

    public function poser(Espace $espace, string $question): ResultatAssistant
    {
        $debut = hrtime(true);
        $extraits = $this->chercher($espace, $question);

        // Rien de pertinent dans le cours : on n'appelle pas l'IA (économise le quota gratuit).
        if ($extraits === []) {
            return new ResultatAssistant(StatutEchangeIa::SANS_SOURCE, null, [], null, $this->depuis($debut));
        }

        // Lève IAIndisponibleException si Gemini et Groq échouent (→ 503 dans le contrôleur).
        $reponse = $this->ia->repondre($this->promptSysteme($espace), $this->message($extraits, $question));

        if (str_contains($reponse->texte, self::REPONSE_INCONNUE)) {
            return new ResultatAssistant(StatutEchangeIa::SANS_SOURCE, null, [], $reponse, $this->depuis($debut));
        }

        return new ResultatAssistant(
            StatutEchangeIa::OK,
            $reponse->texte,
            $this->sourcesCitees($reponse->texte, $extraits),
            $reponse,
            $this->depuis($debut),
        );
    }

    /**
     * Les NB_EXTRAITS fragments de l'espace les plus proches de la question.
     *
     * @return list<array{n: int, fragment_id: int, ressource_id: int, fichier: string, page: ?int, extrait: string, score: float}>
     */
    public function chercher(Espace $espace, string $question): array
    {
        $lignes = DB::getDriverName() === 'pgsql'
            ? $this->chercherPostgres($espace, $question)
            : $this->chercherSimple($espace, $question);

        $extraits = [];
        foreach (array_values($lignes) as $i => $l) {
            $extraits[] = [
                'n' => $i + 1,
                'fragment_id' => (int) $l->id,
                'ressource_id' => (int) $l->ressource_id,
                'fichier' => $l->chemin_fichier ? basename($l->chemin_fichier) : $l->titre,
                'page' => $l->page !== null ? (int) $l->page : null,
                'extrait' => $l->contenu,
                'score' => (float) $l->score,
            ];
        }

        return $extraits;
    }

    /**
     * Recherche plein texte (tsvector french + index GIN).
     * websearch_to_tsquery exige TOUS les mots : on les combine en OU pour qu'une question
     * en langage naturel trouve des passages, et ts_rank classe ceux qui en contiennent le plus.
     */
    private function chercherPostgres(Espace $espace, string $question): array
    {
        return DB::select(<<<'SQL'
            WITH q AS (
                SELECT NULLIF(replace(websearch_to_tsquery('french', ?)::text, ' & ', ' | '), '')::tsquery AS requete
            )
            SELECT f.id, f.ressource_id, f.page, f.contenu, r.chemin_fichier, r.titre,
                   ts_rank(f.recherche, q.requete) AS score
            FROM fragments_document f
            JOIN ressources r ON r.id = f.ressource_id
            JOIN chapitres c ON c.id = r.chapitre_id
            CROSS JOIN q
            WHERE c.espace_id = ? AND f.recherche @@ q.requete
            ORDER BY score DESC, f.id
            LIMIT ?
            SQL, [$question, $espace->id, self::NB_EXTRAITS]);
    }

    /**
     * Repli pour SQLite (tests locaux, sans tsvector) : compte les mots de la question
     * présents dans chaque fragment. La production et la CI utilisent PostgreSQL.
     */
    private function chercherSimple(Espace $espace, string $question): array
    {
        $mots = collect(preg_split('/[^\p{L}\p{N}%]+/u', Str::lower(Str::ascii($question))))
            ->filter(fn ($m) => mb_strlen($m) >= 4)
            ->unique()
            ->values();
        if ($mots->isEmpty()) {
            return [];
        }

        $fragments = DB::table('fragments_document as f')
            ->join('ressources as r', 'r.id', '=', 'f.ressource_id')
            ->join('chapitres as c', 'c.id', '=', 'r.chapitre_id')
            ->where('c.espace_id', $espace->id)
            ->select('f.id', 'f.ressource_id', 'f.page', 'f.contenu', 'r.chemin_fichier', 'r.titre')
            ->orderBy('f.id')
            ->get();

        return $fragments
            ->map(function ($f) use ($mots) {
                $texte = Str::lower(Str::ascii($f->contenu));
                $f->score = $mots->filter(fn ($m) => str_contains($texte, $m))->count() / $mots->count();

                return $f;
            })
            ->filter(fn ($f) => $f->score > 0)
            ->sortByDesc('score')
            ->take(self::NB_EXTRAITS)
            ->all();
    }

    private function promptSysteme(Espace $espace): string
    {
        $consigne = trim((string) $espace->assistant?->instructions);

        return $consigne === ''
            ? self::PROMPT_SYSTEME
            : self::PROMPT_SYSTEME."\nConsigne de l'enseignant : ".$consigne;
    }

    private function message(array $extraits, string $question): string
    {
        $blocs = array_map(
            fn (array $e) => sprintf('[%d] (%s%s) %s', $e['n'], $e['fichier'], $e['page'] !== null ? ', p. '.$e['page'] : '', $e['extrait']),
            $extraits,
        );

        return "Extraits du cours :\n\n".implode("\n\n", $blocs)."\n\nQuestion : ".trim($question);
    }

    /**
     * Numéros cités ([2], [1][3], [1, 3] ou 【2】 des modèles gpt-oss) gardés s'ils existent, sans doublon.
     */
    private function sourcesCitees(string $texte, array $extraits): array
    {
        preg_match_all('/[\[【](\d+(?:\s*,\s*\d+)*)(?:†[^\]】]*)?[\]】]/u', $texte, $m);

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

    private function depuis(int $debut): int
    {
        return (int) round((hrtime(true) - $debut) / 1_000_000);
    }
}
