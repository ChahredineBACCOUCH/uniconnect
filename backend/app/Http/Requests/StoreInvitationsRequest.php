<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SCRUM-88 : inviter une ou plusieurs personnes dans un espace.
 */
class StoreInvitationsRequest extends FormRequest
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
            'user_ids' => ['required', 'array', 'min:1', 'max:50'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Choisissez au moins une personne à inviter.',
            'user_ids.array' => 'La liste des personnes à inviter est invalide.',
            'user_ids.min' => 'Choisissez au moins une personne à inviter.',
            'user_ids.max' => 'Vous pouvez inviter au plus 50 personnes à la fois.',
            'user_ids.*.integer' => 'Une des personnes choisies est invalide.',
            'user_ids.*.distinct' => 'Une personne apparaît deux fois dans la liste.',
            'user_ids.*.exists' => "Une des personnes choisies n'existe pas.",
        ];
    }
}
