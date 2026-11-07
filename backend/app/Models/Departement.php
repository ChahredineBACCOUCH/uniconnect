<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'libelle', 'actif'])]
class Departement extends Model
{
    protected $table = 'departements';

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    /** @return HasMany<Filiere, $this> */
    public function filieres(): HasMany
    {
        return $this->hasMany(Filiere::class);
    }
}
