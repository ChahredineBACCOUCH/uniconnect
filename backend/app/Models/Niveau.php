<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'libelle', 'actif'])]
class Niveau extends Model
{
    // Le pluriel anglais deviné par Laravel serait « niveaus ».
    protected $table = 'niveaux';

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    /** @return HasMany<Classe, $this> */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }
}
