<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extrait du cours cité dans une réponse de l'IA.
 * Accès au document : $source->fragment->ressource (modèles de SCRUM-97).
 */
#[Fillable(['echange_id', 'fragment_id', 'extrait', 'score'])]
class SourceCitee extends Model
{
    protected $table = 'sources_citees';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    /** @return BelongsTo<EchangeIA, $this> */
    public function echange(): BelongsTo
    {
        return $this->belongsTo(EchangeIA::class, 'echange_id');
    }

    /**
     * Le modèle FragmentDocument arrive avec les migrations de SCRUM-97.
     *
     * @return BelongsTo<FragmentDocument, $this>
     */
    public function fragment(): BelongsTo
    {
        return $this->belongsTo(FragmentDocument::class, 'fragment_id');
    }
}
