<?php

namespace App\Exports;

use App\Services\ImportComptesService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * SCRUM-76 : modèle Excel d'import des comptes autorisés.
 *
 * Sans lignes fournies, le fichier contient deux exemples (un étudiant et un
 * enseignant) pour montrer le format attendu.
 */
class ModeleComptesExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array<int, mixed>>  $lignes
     */
    public function __construct(private readonly array $lignes = []) {}

    /**
     * @return array<int, mixed>
     */
    public function headings(): array
    {
        return ImportComptesService::COLONNES;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        if ($this->lignes !== []) {
            return $this->lignes;
        }

        return [
            ['MAT-2026-001', 'Ben Ali', 'Leila', 'leila.benali@univ.tn', 'ETUDIANT', 'L2-INFO-A'],
            ['MAT-2026-002', 'Trabelsi', 'Karim', 'karim.trabelsi@univ.tn', 'ENSEIGNANT', ''],
        ];
    }
}
