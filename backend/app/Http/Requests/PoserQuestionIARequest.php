<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SCRUM-102 : question posée à l'Assistant IA d'un espace (docs/api.md, section 7).
 */
class PoserQuestionIARequest extends FormRequest
{
    public function authorize(): bool
    {
        // Les droits (membre de l'espace, assistant actif) sont vérifiés dans le contrôleur.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:1000'],
            'conversation_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'La question est obligatoire.',
            'question.string' => 'La question doit être un texte.',
            'question.min' => 'La question doit contenir au moins 3 caractères.',
            'question.max' => 'La question ne doit pas dépasser 1 000 caractères.',
            'conversation_id.integer' => 'La conversation est invalide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('question'))) {
            $this->merge(['question' => trim($this->input('question'))]);
        }
    }
}
