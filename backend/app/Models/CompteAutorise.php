<?php

namespace App\Models;

use App\Enums\EtatCompte;
use App\Enums\Profil;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personne autorisée à créer un compte (liste importée par l'administration).
 */
#[Fillable(['matricule', 'nom', 'prenom', 'email', 'profil', 'classe_id', 'etat', 'user_id'])]
class CompteAutorise extends Model
{
    protected $table = 'comptes_autorises';

    protected function casts(): array
    {
        return [
            'profil' => Profil::class,
            'etat' => EtatCompte::class,
        ];
    }

    /** @return BelongsTo<Classe, $this> */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /** Compte utilisateur créé à partir de cette autorisation (null tant qu'il n'existe pas). @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
