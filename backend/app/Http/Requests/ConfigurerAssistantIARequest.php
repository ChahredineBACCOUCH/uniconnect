<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SCRUM-145 : activer ou désactiver l'Assistant IA d'un espace, et sa consigne.
 */
class ConfigurerAssistantIARequest extends FormRequest
{
    public const INSTRUCTIONS_MAX = 1000;

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
            'actif' => ['required', 'boolean'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:'.self::INSTRUCTIONS_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'actif.required' => "Indiquez si l'assistant est activé.",
            'actif.boolean' => 'La valeur « actif » doit être vrai ou faux.',
            'instructions.string' => 'La consigne doit être un texte.',
            'instructions.max' => 'La consigne ne doit pas dépasser 1 000 caractères.',
        ];
    }
}
