<?php

namespace Tests\Feature;

use App\Enums\TypeEspace;
use App\Models\Adhesion;
use App\Models\Annonce;
use App\Models\AssistantIA;
use App\Models\Espace;
use App\Models\FragmentDocument;
use App\Models\Invitation;
use App\Models\Message;
use App\Models\PieceJointe;
use App\Models\Ressource;
use App\Models\User;
use Database\Seeders\DemoProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SCRUM-188 : données de la démonstration du 19/11.
 */
class DemoProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    public function test_le_cours_de_demo_est_pret_pour_l_assistant_ia(): void
    {
        $this->seed(DemoProductionSeeder::class);

        $etudiant = User::where('email', 'etudiant@demo.uniconnect')->firstOrFail();
        $enseignant = User::where('email', 'enseignant@demo.uniconnect')->firstOrFail();
        $cours = Espace::where('type', TypeEspace::ORGANISATIONNEL->value)->where('nom', 'Bases de données')->firstOrFail();

        // L'étudiant et l'enseignante sont membres du groupe de la matière.
        $this->assertTrue(Adhesion::where('espace_id', $cours->id)->where('user_id', $etudiant->id)->exists());
        $this->assertTrue(Adhesion::where('espace_id', $cours->id)->where('user_id', $enseignant->id)->exists());

        // 3 PDF stockés et indexés (fragments par page), plus un lien.
        $pdf = Ressource::whereHas('chapitre', fn ($q) => $q->where('espace_id', $cours->id))->whereNotNull('chemin_fichier')->get();
        $this->assertCount(3, $pdf);
        foreach ($pdf as $ressource) {
            Storage::assertExists($ressource->chemin_fichier);
            $this->assertTrue($ressource->indexee_ia, $ressource->titre.' doit être indexé');
            $this->assertSame([1, 2, 3, 4], FragmentDocument::where('ressource_id', $ressource->id)->distinct()->orderBy('page')->pluck('page')->all());
        }
        $this->assertTrue(AssistantIA::where('espace_id', $cours->id)->where('actif', true)->exists());

        // Discussion, pièce jointe téléchargeable, annonces, invitation en attente.
        $this->assertSame(20, Message::where('espace_id', $cours->id)->count());
        $piece = PieceJointe::where('nom', 'fiche-revision-normalisation.pdf')->firstOrFail();
        Storage::assertExists($piece->chemin);
        $this->assertSame(2, Annonce::where('espace_id', $cours->id)->count());
        $this->assertSame(1, Invitation::where('invite_id', $etudiant->id)->where('etat', 'EN_ATTENTE')->count());
        $this->assertTrue(Espace::where('type', TypeEspace::PRIVE->value)->where('nom', 'Projet intégration – Équipe 4')->exists());
        $this->assertTrue(Espace::where('type', TypeEspace::CHANNEL->value)->where('nom', 'Club Développement web')->exists());
        $this->assertGreaterThan(0, $etudiant->notifications()->count());
    }

    public function test_le_relancer_ne_cree_aucun_doublon(): void
    {
        $this->seed(DemoProductionSeeder::class);

        $compter = fn () => [
            'users' => User::count(),
            'espaces' => Espace::count(),
            'adhesions' => Adhesion::count(),
            'messages' => Message::count(),
            'pieces' => PieceJointe::count(),
            'ressources' => Ressource::count(),
            'fragments' => FragmentDocument::count(),
            'annonces' => Annonce::count(),
            'invitations' => Invitation::count(),
            'notifications' => \DB::table('notifications')->count(),
        ];

        $avant = $compter();
        $this->seed(DemoProductionSeeder::class);

        $this->assertSame($avant, $compter());
    }
}
