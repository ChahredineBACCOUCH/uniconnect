<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'libelle', 'semestre', 'actif'])]
class Matiere extends Model
{
    protected $table = 'matieres';

    protected function casts(): array
    {
        return [
            'semestre' => 'integer',
            'actif' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Classe, $this> */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'classe_matiere');
    }

    /** Enseignants qui assurent cette matière. @return BelongsToMany<User, $this> */
    public function enseignants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'matiere_enseignant');
    }
}
