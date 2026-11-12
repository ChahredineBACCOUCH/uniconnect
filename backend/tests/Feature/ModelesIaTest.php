<?php

namespace Tests\Feature;

use App\Enums\StatutEchangeIa;
use App\Models\ConversationIA;
use App\Models\EchangeIA;
use App\Models\SourceCitee;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

/**
 * SCRUM-101 : tables, relations et casts des modèles de l'Assistant IA, sans base de données.
 */
class ModelesIaTest extends TestCase
{
    public function test_chaque_modele_pointe_sur_la_bonne_table(): void
    {
        $this->assertSame('conversations_ia', (new ConversationIA)->getTable());
        $this->assertSame('echanges_ia', (new EchangeIA)->getTable());
        $this->assertSame('sources_citees', (new SourceCitee)->getTable());
    }

    public function test_relations(): void
    {
        $conversation = new ConversationIA;
        $this->assertInstanceOf(BelongsTo::class, $conversation->user());
        $this->assertInstanceOf(User::class, $conversation->user()->getRelated());
        $this->assertInstanceOf(HasMany::class, $conversation->echanges());
        $this->assertSame('conversation_id', $conversation->echanges()->getForeignKeyName());

        $echange = new EchangeIA;
        $this->assertInstanceOf(ConversationIA::class, $echange->conversation()->getRelated());
        $this->assertSame('conversation_id', $echange->conversation()->getForeignKeyName());
        $this->assertInstanceOf(HasMany::class, $echange->sources());
        $this->assertSame('echange_id', $echange->sources()->getForeignKeyName());

        $this->assertInstanceOf(EchangeIA::class, (new SourceCitee)->echange()->getRelated());
    }

    public function test_casts(): void
    {
        $echange = new EchangeIA([
            'statut' => 'SANS_SOURCE',
            'avis_utile' => 0,
            'tokens_entree' => '2097',
        ]);

        $this->assertSame(StatutEchangeIa::SANS_SOURCE, $echange->statut);
        $this->assertFalse($echange->avis_utile);
        $this->assertSame(2097, $echange->tokens_entree);
        $this->assertNull(EchangeIA::UPDATED_AT);
        $this->assertFalse((new SourceCitee)->usesTimestamps());
        $this->assertSame(0.42, (new SourceCitee(['score' => '0.42']))->score);
    }
}
