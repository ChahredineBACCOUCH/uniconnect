<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réglages de l'Assistant IA d'un espace (0 ou 1 par espace, clé primaire = espace_id).
 */
#[Fillable(['espace_id', 'actif', 'fournisseur', 'modele', 'instructions', 'citer_sources'])]
class AssistantIA extends Model
{
    protected $table = 'assistants_ia';

    protected $primaryKey = 'espace_id';

    public $incrementing = false;

    // La table n'a que updated_at.
    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'citer_sources' => 'boolean',
        ];
    }

    /** @return BelongsTo<Espace, $this> */
    public function espace(): BelongsTo
    {
        return $this->belongsTo(Espace::class);
    }
}
