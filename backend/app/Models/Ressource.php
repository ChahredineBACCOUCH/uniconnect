<?php

namespace App\Models;

use App\Observers\RessourceObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ressource d'un chapitre : fichier (chemin_fichier) ou lien (url).
 * Un PDF est découpé en fragments pour l'Assistant IA (SCRUM-98).
 */
#[Fillable(['chapitre_id', 'auteur_id', 'titre', 'description', 'chemin_fichier', 'url', 'mime', 'indexee_ia'])]
#[ObservedBy(RessourceObserver::class)]
class Ressource extends Model
{
    protected $table = 'ressources';

    protected function casts(): array
    {
        return [
            'indexee_ia' => 'boolean',
        ];
    }

    /** @return BelongsTo<Chapitre, $this> */
    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(Chapitre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /** @return HasMany<FragmentDocument, $this> */
    public function fragments(): HasMany
    {
        return $this->hasMany(FragmentDocument::class)->orderBy('ordre');
    }

    // Nom affiché dans les citations de l'IA : « [1] chap03.pdf · p. 3 ».
    public function nomFichier(): string
    {
        return $this->chemin_fichier ? basename($this->chemin_fichier) : $this->titre;
    }
}
