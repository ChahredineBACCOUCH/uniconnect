<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
    use HasFactory, Notifiable;

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
            'tentatives_echouees' => 'integer',
            'bloque_jusqua' => 'datetime',
            'suspendu_jusqua' => 'datetime',
            'derniere_connexion' => 'datetime',
        ];
    }
}
