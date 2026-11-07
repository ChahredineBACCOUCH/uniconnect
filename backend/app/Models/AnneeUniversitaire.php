<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['libelle', 'date_debut', 'date_fin', 'active'])]
class AnneeUniversitaire extends Model
{
    protected $table = 'annees_universitaires';

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<Classe, $this> */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class, 'annee_id');
    }

    /**
     * Année universitaire active (une seule à la fois) : AnneeUniversitaire::active()->first().
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
