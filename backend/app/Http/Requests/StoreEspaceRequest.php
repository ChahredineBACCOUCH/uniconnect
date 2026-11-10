<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SCRUM-87 : création d'un groupe privé.
 *
 * `duree_visibilite_heures` : absente = durée par défaut des groupes privés ;
 * `null` envoyé explicitement = messages visibles sans limite de durée.
 */
class StoreEspaceRequest extends FormRequest
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
        return [
            'nom' => ['required', 'string', 'min:3', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duree_visibilite_heures' => [
                'nullable',
                'integer',
                Rule::in(config('uniconnect.durees_visibilite_heures')),
            ],
            'duree_courte_autorisee' => ['sometimes', 'boolean'],
            // Maquette E06 « Droit de publication » : true = gestionnaires seulement.
            'mode_annonce' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => "Le nom de l'espace est obligatoire.",
            'nom.min' => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max' => 'Le nom ne doit pas dépasser 80 caractères.',
            'mode_annonce.boolean' => 'Le droit de publication est invalide.',
            'description.max' => 'La description ne doit pas dépasser 1 000 caractères.',
            'duree_visibilite_heures.integer' => 'La durée de visibilité est invalide.',
            'duree_visibilite_heures.in' => "Cette durée de visibilité n'est pas proposée par la plateforme.",
            'duree_courte_autorisee.boolean' => 'La valeur « durée plus courte autorisée » est invalide.',
        ];
    }
}
