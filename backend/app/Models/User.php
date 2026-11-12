<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Droit;
use App\Enums\EtatCompte;
use App\Enums\Profil;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'matricule',
    'nom',
    'prenom',
    'email',
    'password',
    'photo',
    'profil',
    'classe_id',
    'etat',
    'tentatives_echouees',
    'bloque_jusqua',
    'suspendu_jusqua',
    'derniere_connexion',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profil' => Profil::class,
            'etat' => EtatCompte::class,
            'tentatives_echouees' => 'integer',
            'bloque_jusqua' => 'datetime',
            'suspendu_jusqua' => 'datetime',
            'derniere_connexion' => 'datetime',
        ];
    }

    /** @return BelongsTo<Classe, $this> */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /** Autorisation à partir de laquelle le compte a été créé. @return HasOne<CompteAutorise, $this> */
    public function compteAutorise(): HasOne
    {
        return $this->hasOne(CompteAutorise::class);
    }

    /** @return HasMany<DroitUtilisateur, $this> */
    public function droits(): HasMany
    {
        return $this->hasMany(DroitUtilisateur::class);
    }

    /** Adhésions aux espaces, avec le rôle de l'utilisateur. @return HasMany<Adhesion, $this> */
    public function adhesions(): HasMany
    {
        return $this->hasMany(Adhesion::class);
    }

    /** Espaces dont l'utilisateur est membre, avec son rôle dans la table pivot. @return BelongsToMany<Espace, $this> */
    public function espaces(): BelongsToMany
    {
        return $this->belongsToMany(Espace::class, 'adhesions')
            ->withPivot(['role', 'notifications_actives', 'dernier_message_lu_id'])
            ->withTimestamps();
    }

    /** Matières dont l'utilisateur est enseignant (table pivot `matiere_enseignant`). @return BelongsToMany<Matiere, $this> */
    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class, 'matiere_enseignant');
    }

    public function possedeDroit(Droit $droit): bool
    {
        if ($this->relationLoaded('droits')) {
            return $this->droits->contains(fn (DroitUtilisateur $d) => $d->droit === $droit);
        }

        return $this->droits()->where('droit', $droit->value)->exists();
    }

    public function accorderDroit(Droit $droit): void
    {
        if (! $this->possedeDroit($droit)) {
            $this->droits()->create(['droit' => $droit]);
        }
        $this->unsetRelation('droits');
    }

    public function retirerDroit(Droit $droit): void
    {
        // Suppression par requête : DroitUtilisateur a une clé composée (voir le modèle).
        $this->droits()->where('droit', $droit->value)->delete();
        $this->unsetRelation('droits');
    }
}
