<?php

namespace Tests\Feature;

use App\Models\AssistantIA;
use App\Models\Chapitre;
use App\Models\Espace;
use App\Models\FragmentDocument;
use App\Models\Ressource;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

/**
 * SCRUM-97 : tables, relations et casts des chapitres, ressources, fragments et assistants IA.
 */
class ModelesRessourcesTest extends TestCase
{
    public function test_chaque_modele_pointe_sur_la_bonne_table(): void
    {
        $this->assertSame('chapitres', (new Chapitre)->getTable());
        $this->assertSame('ressources', (new Ressource)->getTable());
        $this->assertSame('fragments_document', (new FragmentDocument)->getTable());
        $this->assertSame('assistants_ia', (new AssistantIA)->getTable());
    }

    public function test_relations(): void
    {
        $espace = new Espace;
        $this->assertInstanceOf(Chapitre::class, $espace->chapitres()->getRelated());
        $this->assertInstanceOf(HasManyThrough::class, $espace->ressources());
        $this->assertInstanceOf(HasOne::class, $espace->assistant());
        $this->assertSame('espace_id', $espace->assistant()->getForeignKeyName());

        $this->assertSame('chapitre_id', (new Chapitre)->ressources()->getForeignKeyName());
        $this->assertSame('ressource_id', (new Ressource)->fragments()->getForeignKeyName());
        $this->assertSame('auteur_id', (new Ressource)->auteur()->getForeignKeyName());
        $this->assertInstanceOf(Ressource::class, (new FragmentDocument)->ressource()->getRelated());
    }

    public function test_cles_et_horodatages(): void
    {
        $assistant = new AssistantIA;
        $this->assertSame('espace_id', $assistant->getKeyName());
        $this->assertFalse($assistant->getIncrementing());
        $this->assertNull(AssistantIA::CREATED_AT);
        $this->assertNull(FragmentDocument::UPDATED_AT);
    }

    public function test_nom_du_fichier_cite(): void
    {
        $this->assertSame('chap03.pdf', (new Ressource(['chemin_fichier' => 'ressources/12/chap03.pdf', 'titre' => 'Cours']))->nomFichier());
        $this->assertSame('Vidéo du cours', (new Ressource(['url' => 'https://exemple.tn', 'titre' => 'Vidéo du cours']))->nomFichier());
    }
}
