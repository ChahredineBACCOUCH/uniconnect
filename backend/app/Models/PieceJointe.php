<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fichier attaché à un message.
 * La table `pieces_jointes` sera créée par son propre ticket ; ce modèle est déclaré
 * ici pour la relation Message::piecesJointes().
 */
#[Fillable([
    'message_id',
    'nom',
    'mime',
    'taille_octets',
    'chemin',
    'expire_le',
])]
class PieceJointe extends Model
{
    protected $table = 'pieces_jointes';

    /** La table ne contient que `created_at`, sans `updated_at`. */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'taille_octets' => 'integer',
            'expire_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
