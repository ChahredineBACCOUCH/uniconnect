<?php

namespace App\Models;

use App\Enums\EtatInvitation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SCRUM-88 : invitation d'une personne dans un espace.
 */
#[Fillable([
    'espace_id',
    'invite_id',
    'invite_par_id',
    'etat',
    'expire_le',
])]
class Invitation extends Model
{
    protected $table = 'invitations';

    protected function casts(): array
    {
        return [
            'etat' => EtatInvitation::class,
            'expire_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Espace, $this> */
    public function espace(): BelongsTo
    {
        return $this->belongsTo(Espace::class);
    }

    /** Personne invitée. @return BelongsTo<User, $this> */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invite_id');
    }

    /** Personne qui a envoyé l'invitation. @return BelongsTo<User, $this> */
    public function invitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invite_par_id');
    }

    /** Invitations en attente et pas encore expirées. */
    public function scopeValides(Builder $query): void
    {
        $query->where('etat', EtatInvitation::EN_ATTENTE)
            ->where('expire_le', '>', now());
    }

    public function estExpiree(): bool
    {
        return $this->expire_le->isPast();
    }
}
