<?php

namespace App\Models;

use App\Enums\Droit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de la table `droits_utilisateur` (clé primaire composée user_id + droit).
 *
 * Eloquent ne gère pas les clés composées : on n'appelle jamais save() ou delete()
 * sur une instance. On passe par User::accorderDroit() et User::retirerDroit().
 */
#[Fillable(['user_id', 'droit'])]
class DroitUtilisateur extends Model
{
    protected $table = 'droits_utilisateur';

    public $incrementing = false;

    // La table n'a que `accorde_le` (rempli par la base), pas created_at / updated_at.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'droit' => Droit::class,
            'accorde_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
