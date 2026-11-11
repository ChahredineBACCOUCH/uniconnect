<?php

namespace App\IA;

use App\Enums\StatutEchangeIa;

/**
 * Résultat de AssistantService::poser(), prêt à être enregistré (echanges_ia, sources_citees)
 * et renvoyé par l'API (SCRUM-102).
 */
final readonly class ResultatAssistant
{
    /**
     * $sources : extraits réellement cités par l'IA, numérotés comme dans sa réponse.
     *
     * @param  list<array{n: int, fragment_id: int, ressource_id: int, fichier: string, page: ?int, extrait: string, score: float}>  $sources
     */
    public function __construct(
        public StatutEchangeIa $statut,
        public ?string $reponse,
        public array $sources,
        public ?ReponseIA $ia,
        public int $dureeMs,
    ) {}

    /** Sources au format de l'API (docs/api.md, section 7). */
    public function sourcesPourApi(): array
    {
        return array_map(fn (array $s) => [
            'n' => $s['n'],
            'fichier' => $s['fichier'],
            'page' => $s['page'],
            'ressource_id' => $s['ressource_id'],
        ], $this->sources);
    }
}
