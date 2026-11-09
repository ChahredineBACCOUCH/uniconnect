<?php

namespace App\Models;

use App\Enums\RoleMembre;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adhésion d'un utilisateur à un espace (table `adhesions`).
 * Une seule ligne par couple (espace, utilisateur), avec un rôle.
 */
#[Fillable([
    'espace_id',
    'user_id',
    'role',
    'notifications_actives',
    'dernier_message_lu_id',
])]
class Adhesion extends Model
{
    protected $table = 'adhesions';

    protected function casts(): array
    {
        return [
            'role' => RoleMembre::class,
            'notifications_actives' => 'boolean',
            'dernier_message_lu_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Espace, $this> */
    public function espace(): BelongsTo
    {
        return $this->belongsTo(Espace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
