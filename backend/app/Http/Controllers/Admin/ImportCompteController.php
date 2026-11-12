<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ModeleComptesExport;
use App\Http\Controllers\Controller;
use App\Services\ImportComptesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * SCRUM-76 : import des comptes autorisés (aperçu, validation, modèle Excel).
 */
class ImportCompteController extends Controller
{
    /**
     * Messages en français : l'application n'a pas de fichiers de traduction,
     * Laravel afficherait sinon « The fichier field is required. ».
     *
     * @var array<string, string>
     */
    private const MESSAGES = [
        'fichier.required' => 'Le fichier est obligatoire.',
        'fichier.file' => "Le fichier n'a pas pu être téléversé.",
        'fichier.extensions' => 'Le fichier doit être au format Excel (.xlsx ou .xls).',
        'fichier.max' => 'Le fichier ne peut pas dépasser 10 Mo.',
        'lignes.required' => 'Aucune ligne à importer.',
        'lignes.array' => 'Les lignes à importer sont invalides.',
        'lignes.min' => 'Aucune ligne à importer.',
        'lignes.max' => 'Le fichier ne peut pas contenir plus de 2000 lignes.',
        'lignes.*.ligne.integer' => 'Le numéro de ligne est invalide.',
        'lignes.*.ligne.min' => 'Le numéro de ligne est invalide.',
        'lignes.*.matricule.required' => 'Le matricule est obligatoire.',
        'lignes.*.nom.required' => 'Le nom est obligatoire.',
        'lignes.*.prenom.required' => 'Le prénom est obligatoire.',
        'lignes.*.email.required' => "L'adresse e-mail est obligatoire.",
        'lignes.*.profil.required' => 'Le profil est obligatoire.',
        'fichier_nom.string' => 'Le nom du fichier est invalide.',
        'fichier_nom.max' => 'Le nom du fichier ne peut pas dépasser 255 caractères.',
    ];

    public function __construct(private readonly ImportComptesService $service) {}

    /**
     * Aperçu du fichier : aucune donnée n'est enregistrée.
     */
    public function apercu(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'fichier' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
        ], self::MESSAGES);

        return response()->json($this->service->apercu($donnees['fichier']));
    }

    /**
     * Enregistre les lignes validées lors de l'aperçu.
     */
    public function valider(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'lignes' => ['required', 'array', 'min:1', 'max:2000'],
            'lignes.*.ligne' => ['sometimes', 'integer', 'min:1'],
            'lignes.*.matricule' => ['required', 'string'],
            'lignes.*.nom' => ['required', 'string'],
            'lignes.*.prenom' => ['required', 'string'],
            'lignes.*.email' => ['required', 'string'],
            'lignes.*.profil' => ['required', 'string'],
            'lignes.*.classe_code' => ['sometimes', 'nullable', 'string'],
            'fichier_nom' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], self::MESSAGES);

        return response()->json(
            $this->service->valider($donnees['lignes'], $donnees['fichier_nom'] ?? null),
            201,
        );
    }

    /**
     * Télécharge le modèle Excel avec les colonnes attendues.
     */
    public function modele(): BinaryFileResponse
    {
        return Excel::download(new ModeleComptesExport, 'modele-import-comptes.xlsx');
    }
}
