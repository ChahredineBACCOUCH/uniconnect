<?php

namespace Tests\Feature;

use App\Models\Espace;
use App\Models\Message;
use App\Models\PieceJointe;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SCRUM-93 : table messages, index de chargement / recherche / purge et relations Eloquent.
 */
class MessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_tables_et_colonnes_attendues_existent(): void
    {
        $this->assertTrue(Schema::hasTable('messages'));

        $colonnes = [
            'id', 'espace_id', 'auteur_id', 'parent_id', 'contenu',
            'epingle', 'marque_utile', 'masque', 'modifie_le', 'expire_le',
            'created_at', 'updated_at',
        ];

        foreach ($colonnes as $colonne) {
            $this->assertTrue(Schema::hasColumn('messages', $colonne), "Colonne messages.$colonne absente");
        }
    }

    public function test_les_relations_du_modele_message(): void
    {
        $message = new Message;

        $this->assertRelation($message->espace(), BelongsTo::class, Espace::class, 'espace_id');
        $this->assertRelation($message->auteur(), BelongsTo::class, User::class, 'auteur_id');
        $this->assertRelation($message->parent(), BelongsTo::class, Message::class, 'parent_id');
        $this->assertRelation($message->reponses(), HasMany::class, Message::class, 'parent_id');
        $this->assertRelation($message->piecesJointes(), HasMany::class, PieceJointe::class, 'message_id');
    }

    public function test_les_valeurs_par_defaut_et_les_casts(): void
    {
        [$espace, $auteur] = $this->contexte();

        $message = Message::create([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'contenu' => 'Bonjour tout le monde',
        ]);
        $message->refresh();

        $this->assertFalse($message->epingle);
        $this->assertFalse($message->marque_utile);
        $this->assertFalse($message->masque);
        $this->assertNull($message->modifie_le);
        $this->assertNull($message->expire_le);
        $this->assertNotNull($message->created_at);
        $this->assertSame($espace->id, $message->espace->id);
        $this->assertSame($auteur->id, $message->auteur->id);
    }

    public function test_un_message_peut_repondre_a_un_autre_message(): void
    {
        [$espace, $auteur] = $this->contexte();

        $parent = Message::create([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'contenu' => 'Question sur le chapitre 3',
        ]);

        $reponse = Message::create([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'parent_id' => $parent->id,
            'contenu' => 'Voici la réponse',
        ]);

        $this->assertSame($parent->id, $reponse->parent->id);
        $this->assertCount(1, $parent->reponses);
        $this->assertSame($reponse->id, $parent->reponses->first()->id);
    }

    public function test_supprimer_le_parent_detache_la_reponse(): void
    {
        [$espace, $auteur] = $this->contexte();

        $parent = Message::create([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'contenu' => 'Message initial',
        ]);

        $reponse = Message::create([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'parent_id' => $parent->id,
            'contenu' => 'Réponse',
        ]);

        $parent->delete();

        $this->assertNull($reponse->fresh()->parent_id);
    }

    public function test_la_colonne_de_recherche_est_generee_en_francais(): void
    {
        $this->skipSiSqlite();

        $this->assertTrue(Schema::hasColumn('messages', 'recherche'));

        [$espace, $auteur] = $this->contexte();

        Message::create([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'contenu' => 'Les bases de données relationnelles sont intéressantes',
        ]);

        $trouve = DB::table('messages')
            ->whereRaw("recherche @@ plainto_tsquery('french', ?)", ['donnée'])
            ->exists();

        $this->assertTrue($trouve, 'La recherche doit retrouver le message à partir d’un mot fléchi.');
    }

    public function test_les_index_de_chargement_de_recherche_et_de_purge_existent(): void
    {
        $this->skipSiSqlite();

        $index = DB::table('pg_indexes')
            ->where('tablename', 'messages')
            ->pluck('indexdef', 'indexname');

        $this->assertArrayHasKey('messages_fil', $index->all());
        $this->assertStringContainsString('(espace_id, id DESC)', $index['messages_fil']);

        $this->assertArrayHasKey('messages_a_purger', $index->all());
        $this->assertStringContainsString('WHERE', $index['messages_a_purger']);

        $this->assertArrayHasKey('messages_recherche', $index->all());
        $this->assertStringContainsString('gin', strtolower($index['messages_recherche']));
    }

    /**
     * @return array{Espace, User}
     */
    private function contexte(): array
    {
        $espace = Espace::create(['type' => 'PRIVE', 'nom' => 'Groupe de révision']);
        $auteur = User::factory()->create();

        return [$espace, $auteur];
    }

    private function assertRelation(object $relation, string $type, string $modele, string $cle): void
    {
        $this->assertInstanceOf($type, $relation);
        $this->assertInstanceOf($modele, $relation->getRelated());
        $this->assertSame($cle, $relation->getForeignKeyName());
    }

    /**
     * La colonne générée et les index GIN n'existent que sur PostgreSQL (comme la CI).
     */
    private function skipSiSqlite(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Colonne tsvector et index GIN testés sur PostgreSQL.');
        }
    }
}
