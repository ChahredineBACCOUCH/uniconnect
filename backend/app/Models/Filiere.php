<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'libelle', 'departement_id', 'actif'])]
class Filiere extends Model
{
    protected $table = 'filieres';

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Departement, $this> */
    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    /** @return HasMany<Classe, $this> */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }
}
