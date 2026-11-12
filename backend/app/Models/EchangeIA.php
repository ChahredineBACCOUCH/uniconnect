<?php

namespace App\Models;

use App\Enums\StatutEchangeIa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une question posée à l'Assistant IA, sa réponse et ses mesures (tokens, durée, fournisseur).
 */
#[Fillable([
    'conversation_id',
    'question',
    'reponse',
    'avis_utile',
    'tokens_entree',
    'tokens_sortie',
    'duree_ms',
    'fournisseur',
    'statut',
])]
class EchangeIA extends Model
{
    protected $table = 'echanges_ia';

    // La table n'a que created_at (rempli par la base si absent).
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'statut' => StatutEchangeIa::class,
            'avis_utile' => 'boolean',
            'tokens_entree' => 'integer',
            'tokens_sortie' => 'integer',
            'duree_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ConversationIA, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ConversationIA::class, 'conversation_id');
    }

    /** @return HasMany<SourceCitee, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(SourceCitee::class, 'echange_id');
    }
}
