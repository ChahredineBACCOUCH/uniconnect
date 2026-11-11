<?php

namespace App\Http\Requests;

use App\Models\Espace;
use App\Models\Message;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * SCRUM-94 : envoi d'un message dans un espace.
 */
class StoreMessageRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', 'min:1'],
            // Durée de visibilité plus courte que celle de l'espace, si autorisée.
            'duree_visibilite_heures' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Règles qui dépendent de l'espace ciblé (route `/espaces/{espace}/messages`).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $espace = $this->route('espace');

                if (! $espace instanceof Espace) {
                    return;
                }

                $this->verifierParent($validator, $espace);
                $this->verifierDureeCourte($validator, $espace);
            },
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
            'parent_id.integer' => 'Le message parent est invalide.',
            'parent_id.min' => 'Le message parent est invalide.',
            'duree_visibilite_heures.integer' => 'La durée de visibilité est invalide.',
            'duree_visibilite_heures.min' => 'La durée de visibilité doit être d\'au moins une heure.',
        ];
    }

    private function verifierParent(Validator $validator, Espace $espace): void
    {
        if (! $this->filled('parent_id')) {
            return;
        }

        $existe = Message::query()
            ->where('espace_id', $espace->id)
            ->whereKey($this->integer('parent_id'))
            ->exists();

        if (! $existe) {
            $validator->errors()->add(
                'parent_id',
                "Le message parent n'existe pas dans cet espace.",
            );
        }
    }

    private function verifierDureeCourte(Validator $validator, Espace $espace): void
    {
        if (! $this->filled('duree_visibilite_heures')) {
            return;
        }

        if (! $espace->duree_courte_autorisee) {
            $validator->errors()->add(
                'duree_visibilite_heures',
                "Cet espace n'autorise pas les durées de visibilité plus courtes.",
            );

            return;
        }

        if (
            $espace->duree_visibilite_heures !== null
            && $this->integer('duree_visibilite_heures') >= $espace->duree_visibilite_heures
        ) {
            $validator->errors()->add(
                'duree_visibilite_heures',
                'La durée choisie doit être plus courte que celle de l\'espace.',
            );
        }
    }
}
