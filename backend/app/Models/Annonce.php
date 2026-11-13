<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SCRUM-123 : annonce officielle publiée dans un espace.
 *
 * Séparée de la discussion, elle est réservée aux gestionnaires de l'espace.
 */
#[Fillable([
    'espace_id',
    'auteur_id',
    'titre',
    'contenu',
    'notifier_email',
])]
class Annonce extends Model
{
    protected $table = 'annonces';

    protected function casts(): array
    {
        return [
            'notifier_email' => 'boolean',
        ];
    }

    /** @return BelongsTo<Espace, $this> */
    public function espace(): BelongsTo
    {
        return $this->belongsTo(Espace::class);
    }

    /** Auteur de l'annonce (NULL si le compte a été supprimé). @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
