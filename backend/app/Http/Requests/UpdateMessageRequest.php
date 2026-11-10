<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SCRUM-94 : modification du contenu d'un message par son auteur.
 */
class UpdateMessageRequest extends FormRequest
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
            'contenu' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contenu.required' => 'Le contenu du message est obligatoire.',
            'contenu.string' => 'Le contenu du message est invalide.',
            'contenu.max' => 'Le message ne doit pas dépasser 10 000 caractères.',
        ];
    }
}
