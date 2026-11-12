<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * SCRUM-76 : lecture brute d'une feuille Excel.
 *
 * Le service d'import interprète lui-même l'en-tête et les colonnes afin de
 * produire des messages d'erreur en français, ligne par ligne.
 */
class LignesImport implements ToArray
{
    /**
     * @param  array<array-key, mixed>  $array
     */
    public function array(array $array): void
    {
        // Les lignes sont récupérées via `Excel::toArray()`.
    }
}
