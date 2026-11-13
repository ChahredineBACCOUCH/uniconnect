<?php

namespace App\Models;

/**
 * Règles de la plateforme applicables aux fichiers joints (SCRUM-136).
 *
 * La table `regles_plateforme` (docs/conception/schema.sql) n'existe pas encore :
 * tant que l'écran d'administration `/admin/regles` n'est pas livré, les valeurs
 * sont lues dans `config/uniconnect.php`.
 */
final class ReglePlateforme
{
    /**
     * @param  array<int, string>  $formats_autorises
     */
    private function __construct(
        public readonly int $taille_max_mo,
        public readonly array $formats_autorises,
    ) {}

    /**
     * Règles en vigueur pour la requête courante.
     */
    public static function actuelle(): self
    {
        return new self(
            (int) config('uniconnect.fichiers.taille_max_mo', 25),
            array_values((array) config('uniconnect.fichiers.formats_autorises', [])),
        );
    }

    /**
     * Taille maximale d'un fichier en kilooctets (unité de la règle `max`).
     */
    public function taille_max_ko(): int
    {
        return $this->taille_max_mo * 1024;
    }

    /**
     * Formats autorisés, prêts pour la règle `mimes:a,b,c`.
     */
    public function formats(): string
    {
        return implode(',', $this->formats_autorises);
    }
}
