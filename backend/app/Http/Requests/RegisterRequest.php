<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SCRUM-62 : formulaire d'inscription (contrôle d'éligibilité avant création du compte).
 */
class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'matricule' => ['required', 'string', 'max:30'],
            'nom' => ['nullable', 'string', 'max:80'],
            'prenom' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'charte' => ['accepted'],
        ];
    }

    /**
     * Messages d'erreur en français.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'matricule.required' => 'Le matricule est obligatoire.',
            'matricule.string' => 'Le matricule est invalide.',
            'matricule.max' => 'Le matricule ne doit pas dépasser 30 caractères.',
            'nom.string' => 'Le nom est invalide.',
            'nom.max' => 'Le nom ne doit pas dépasser 80 caractères.',
            'prenom.string' => 'Le prénom est invalide.',
            'prenom.max' => 'Le prénom ne doit pas dépasser 80 caractères.',
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.string' => "L'adresse e-mail est invalide.",
            'email.email' => "Le format de l'adresse e-mail est invalide.",
            'email.max' => "L'adresse e-mail ne doit pas dépasser 190 caractères.",
            'charte.accepted' => 'Vous devez accepter la charte pour créer un compte.',
        ];
    }
}
