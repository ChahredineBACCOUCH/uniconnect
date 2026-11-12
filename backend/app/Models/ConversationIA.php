<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation d'un étudiant avec l'Assistant IA d'un espace.
 */
#[Fillable(['espace_id', 'user_id', 'titre'])]
class ConversationIA extends Model
{
    protected $table = 'conversations_ia';

    /**
     * Le modèle Espace arrive avec les migrations espaces (SCRUM-81).
     *
     * @return BelongsTo<Espace, $this>
     */
    public function espace(): BelongsTo
    {
        return $this->belongsTo(Espace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EchangeIA, $this> */
    public function echanges(): HasMany
    {
        return $this->hasMany(EchangeIA::class, 'conversation_id');
    }
}
