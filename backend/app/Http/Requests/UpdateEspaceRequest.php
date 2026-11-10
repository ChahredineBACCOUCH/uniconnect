<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SCRUM-87 : modification du nom et de la description d'un espace.
 */
class UpdateEspaceRequest extends FormRequest
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
            'nom' => ['sometimes', 'required', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => "Le nom de l'espace est obligatoire.",
            'nom.max' => 'Le nom ne doit pas dépasser 80 caractères.',
            'description.max' => 'La description ne doit pas dépasser 1 000 caractères.',
        ];
    }
}
