<?php

namespace App\Http\Requests;

use App\Models\ReglePlateforme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SCRUM-124 : publication d'une annonce, avec ou sans pièces jointes.
 */
class StoreAnnonceRequest extends FormRequest
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
            'contenu' => ['required', 'string', 'max:10000'],
            'notifier_email' => ['sometimes', 'boolean'],
            // SCRUM-124 : jusqu'à 5 fichiers, chacun limité en taille et en format.
            'fichiers' => ['nullable', 'array', 'max:5'],
            'fichiers.*' => [
                'file',
                'max:'.$regle->taille_max_ko(),
                'mimes:'.$regle->formats(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $regle = ReglePlateforme::actuelle();

        return [
            'titre.required' => "Le titre de l'annonce est obligatoire.",
            'titre.string' => "Le titre de l'annonce est invalide.",
            'titre.max' => 'Le titre ne doit pas dépasser 160 caractères.',
            'contenu.required' => "Le contenu de l'annonce est obligatoire.",
            'contenu.string' => "Le contenu de l'annonce est invalide.",
            'contenu.max' => "L'annonce ne doit pas dépasser 10 000 caractères.",
            'notifier_email.boolean' => 'La case « notifier par e-mail » est invalide.',
            'fichiers.array' => 'Les fichiers joints sont invalides.',
            'fichiers.max' => 'Vous ne pouvez joindre que 5 fichiers au maximum.',
            'fichiers.*.file' => "Le fichier joint n'a pas pu être téléversé.",
            'fichiers.*.max' => "Le fichier dépasse la taille maximale de {$regle->taille_max_mo} Mo",
            'fichiers.*.mimes' => "Ce format de fichier n'est pas autorisé.",
        ];
    }
}
