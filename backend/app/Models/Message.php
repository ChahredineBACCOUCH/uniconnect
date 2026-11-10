<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Message publié dans un espace, éventuellement en réponse à un autre message.
 * La colonne `recherche` (tsvector) est générée par PostgreSQL : elle n'est jamais écrite.
 */
#[Fillable([
    'espace_id',
    'auteur_id',
    'parent_id',
    'contenu',
    'epingle',
    'marque_utile',
    'masque',
    'modifie_le',
    'expire_le',
])]
class Message extends Model
{
    protected $table = 'messages';

    protected function casts(): array
    {
        return [
            'epingle' => 'boolean',
            'marque_utile' => 'boolean',
            'masque' => 'boolean',
            'modifie_le' => 'datetime',
            'expire_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Espace, $this> */
    public function espace(): BelongsTo
    {
        return $this->belongsTo(Espace::class);
    }

    /** Auteur du message (NULL si le compte a été supprimé). @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /** Message auquel celui-ci répond. @return BelongsTo<Message, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    /** Réponses à ce message. @return HasMany<Message, $this> */
    public function reponses(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    /** @return HasMany<PieceJointe, $this> */
    public function piecesJointes(): HasMany
    {
        return $this->hasMany(PieceJointe::class);
    }
}
