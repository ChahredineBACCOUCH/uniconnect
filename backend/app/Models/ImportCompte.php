<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SCRUM-76 : trace d'un import de comptes autorisés.
 */
#[Fillable(['user_id', 'fichier', 'total_lignes', 'importes', 'ignores', 'erreurs'])]
class ImportCompte extends Model
{
    protected $table = 'imports_comptes';

    protected function casts(): array
    {
        return [
            'erreurs' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
