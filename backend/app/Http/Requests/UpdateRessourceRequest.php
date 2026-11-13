<?php

namespace App\Http\Requests;

use App\Models\ReglePlateforme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * SCRUM-126 : modification d'une ressource (titre, description, fichier ou lien).
 *
 * Sans fichier ni lien, seuls les champs fournis sont modifiés. Fournir un
 * fichier remplace le lien, et inversement.
 */
class UpdateRessourceRequest extends FormRequest
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
            'titre' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'fichier' => [
                'nullable',
                'file',
                'max:'.$regle->taille_max_ko(),
                'mimes:'.$regle->formats(),
            ],
            'url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->hasFile('fichier') && $this->filled('url')) {
                    $validator->errors()->add('fichier', 'Une ressource est un fichier OU un lien, pas les deux.');
                    $validator->errors()->add('url', 'Une ressource est un fichier OU un lien, pas les deux.');
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
            'fichier.file' => "Le fichier n'a pas pu être téléversé.",
            'fichier.max' => "Le fichier dépasse la taille maximale de {$regle->taille_max_mo} Mo",
            'fichier.mimes' => "Ce format de fichier n'est pas autorisé.",
            'url.url' => 'Le lien est invalide.',
            'url.max' => 'Le lien ne doit pas dépasser 500 caractères.',
        ];
    }
}
