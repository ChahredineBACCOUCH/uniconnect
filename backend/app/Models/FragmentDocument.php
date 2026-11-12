<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Morceau de texte (≈ 1 500 caractères) d'une ressource, avec son numéro de page.
 * La colonne `recherche` (tsvector, PostgreSQL) est calculée par la base : on ne l'écrit jamais.
 */
#[Fillable(['ressource_id', 'page', 'ordre', 'contenu'])]
#[Hidden(['recherche'])]
class FragmentDocument extends Model
{
    protected $table = 'fragments_document';

    // La table n'a que created_at.
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'ordre' => 'integer',
        ];
    }

    /** @return BelongsTo<Ressource, $this> */
    public function ressource(): BelongsTo
    {
        return $this->belongsTo(Ressource::class);
    }
}
