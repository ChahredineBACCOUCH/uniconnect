<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chapitre d'un espace, qui regroupe des ressources du cours.
 */
#[Fillable(['espace_id', 'titre', 'ordre'])]
class Chapitre extends Model
{
    protected $table = 'chapitres';

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
        ];
    }

    /** @return BelongsTo<Espace, $this> */
    public function espace(): BelongsTo
    {
        return $this->belongsTo(Espace::class);
    }

    /** @return HasMany<Ressource, $this> */
    public function ressources(): HasMany
    {
        return $this->hasMany(Ressource::class);
    }
}
