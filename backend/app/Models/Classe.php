<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'libelle', 'filiere_id', 'niveau_id', 'annee_id', 'actif'])]
class Classe extends Model
{
    protected $table = 'classes';

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Filiere, $this> */
    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    /** @return BelongsTo<Niveau, $this> */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /** @return BelongsTo<AnneeUniversitaire, $this> */
    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeUniversitaire::class, 'annee_id');
    }

    /** @return BelongsToMany<Matiere, $this> */
    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class, 'classe_matiere');
    }

    /** Utilisateurs rattachés à la classe (les étudiants). @return HasMany<User, $this> */
    public function etudiants(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<CompteAutorise, $this> */
    public function comptesAutorises(): HasMany
    {
        return $this->hasMany(CompteAutorise::class);
    }
}
