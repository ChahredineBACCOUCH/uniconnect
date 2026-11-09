<?php

namespace App\Models;

use App\Enums\EtatEspace;
use App\Enums\ModeAcces;
use App\Enums\TypeEspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Espace de communication : groupe organisationnel, groupe privé ou channel.
 * Les trois types partagent la même table (héritage UML mappé en `type`).
 */
#[Fillable([
    'type',
    'nom',
    'description',
    'image',
    'etat',
    'duree_visibilite_heures',
    'duree_courte_autorisee',
    'mode_acces',
    'mode_annonce',
    'audience',
    'annee_id',
    'classe_id',
    'matiere_id',
    'created_by',
])]
class Espace extends Model
{
    protected $table = 'espaces';

    protected function casts(): array
    {
        return [
            'type' => TypeEspace::class,
            'etat' => EtatEspace::class,
            'duree_visibilite_heures' => 'integer',
            'duree_courte_autorisee' => 'boolean',
            'mode_acces' => ModeAcces::class,
            'mode_annonce' => 'boolean',
            'audience' => 'array',
        ];
    }

    /** Adhésions (une ligne par membre, avec son rôle). @return HasMany<Adhesion, $this> */
    public function adhesions(): HasMany
    {
        return $this->hasMany(Adhesion::class);
    }

    /** Membres de l'espace, avec leur rôle dans la table pivot `adhesions`. @return BelongsToMany<User, $this> */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'adhesions')
            ->withPivot(['role', 'notifications_actives', 'dernier_message_lu_id'])
            ->withTimestamps();
    }

    /** @return BelongsTo<AnneeUniversitaire, $this> */
    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeUniversitaire::class, 'annee_id');
    }

    /** @return BelongsTo<Classe, $this> */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /** @return BelongsTo<Matiere, $this> */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    /** Compte qui a créé l'espace. @return BelongsTo<User, $this> */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
