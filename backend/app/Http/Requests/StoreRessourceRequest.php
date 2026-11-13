<?php

namespace App\Http\Requests;

use App\Models\ReglePlateforme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * SCRUM-126 : ajout d'une ressource à un chapitre.
 *
 * Une ressource est un fichier OU un lien, jamais les deux. Le fichier est
 * limité par les règles de la plateforme (taille et formats).
 */
class StoreRessourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $regle = ReglePlateforme::actuelle();

        return [
            'titre' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Uniquement pour POST /espaces/{id}/ressources ; ignoré quand le
            // chapitre est déjà dans l'URL (POST /chapitres/{id}/ressources).
            'chapitre_id' => ['nullable', 'integer', 'min:1'],
            'fichier' => [
                'nullable',
                'file',
                'max:'.$regle->taille_max_ko(),
                'mimes:'.$regle->formats(),
            ],
            'url' => ['nullable', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $aFichier = $this->hasFile('fichier');
                $aUrl = $this->filled('url');

                if ($aFichier && $aUrl) {
                    $validator->errors()->add('fichier', 'Une ressource est un fichier OU un lien, pas les deux.');
                    $validator->errors()->add('url', 'Une ressource est un fichier OU un lien, pas les deux.');
                }

                if (! $aFichier && ! $aUrl) {
                    $validator->errors()->add('fichier', 'Ajoutez un fichier ou un lien.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $regle = ReglePlateforme::actuelle();

        return [
            'titre.required' => 'Le titre de la ressource est obligatoire.',
            'titre.string' => 'Le titre de la ressource est invalide.',
            'titre.max' => 'Le titre ne doit pas dépasser 160 caractères.',
            'description.string' => 'La description de la ressource est invalide.',
            'description.max' => 'La description ne doit pas dépasser 5 000 caractères.',
            'chapitre_id.integer' => 'Le chapitre est invalide.',
            'chapitre_id.min' => 'Le chapitre est invalide.',
            'fichier.file' => "Le fichier n'a pas pu être téléversé.",
            'fichier.max' => "Le fichier dépasse la taille maximale de {$regle->taille_max_mo} Mo",
            'fichier.mimes' => "Ce format de fichier n'est pas autorisé.",
            'url.url' => 'Le lien est invalide.',
            'url.max' => 'Le lien ne doit pas dépasser 500 caractères.',
        ];
    }
}
