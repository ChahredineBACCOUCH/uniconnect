<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fichier attaché à un message (SCRUM-136).
 *
 * Le fichier lui-même est stocké sur le disque (`chemin`) ; la ligne ne conserve
 * que les métadonnées utiles à l'affichage et au téléchargement.
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
