<?php

namespace Tests\Feature;

use App\Enums\RoleMembre;
use App\Models\Adhesion;
use App\Models\Espace;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-85 : GET /api/me/espaces (menu latéral et accueil).
 */
class MesEspacesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_ne_voit_que_ses_espaces_regroupes_par_type(): void
    {
        $moi = User::factory()->create();
        $prive = $this->espace('PRIVE', 'Révisions BD');
        $channel = $this->espace('CHANNEL', 'Club Informatique', ['mode_annonce' => true, 'mode_acces' => 'OUVERT']);
        $this->espace('PRIVE', 'Groupe des autres'); // pas membre

        $this->adherer($prive, $moi, RoleMembre::PROPRIETAIRE);
        $this->adherer($channel, $moi);

        Sanctum::actingAs($moi);

        $this->getJson('/api/me/espaces')
            ->assertOk()
            ->assertJsonCount(0, 'organisationnels')
            ->assertJsonCount(1, 'prives')
            ->assertJsonCount(1, 'channels')
            ->assertJsonPath('prives.0.id', $prive->id)
            ->assertJsonPath('prives.0.nom', 'Révisions BD')
            ->assertJsonPath('prives.0.role', 'PROPRIETAIRE')
            ->assertJsonPath('prives.0.icone', 'mdi-lock-outline')
            ->assertJsonPath('prives.0.visibilite_limitee', true)
            ->assertJsonPath('channels.0.icone', 'mdi-bullhorn-outline')
            ->assertJsonPath('channels.0.role', 'MEMBRE');
    }

    public function test_le_compteur_de_non_lus_est_juste_apres_trois_messages(): void
    {
        $moi = User::factory()->create();
        $autre = User::factory()->create();
        $espace = $this->espace('PRIVE', 'Projet');
        $this->adherer($espace, $moi);
        $this->adherer($espace, $autre);

        $lu = $this->message($espace, $autre);
        Adhesion::where('espace_id', $espace->id)->where('user_id', $moi->id)
            ->update(['dernier_message_lu_id' => $lu->id]);

        // 3 nouveaux messages des autres : comptés.
        $this->message($espace, $autre);
        $this->message($espace, $autre);
        $this->message($espace, $autre);
        // Non comptés : le mien, un message masqué, un message expiré.
        $this->message($espace, $moi);
        $this->message($espace, $autre, ['masque' => true]);
        $this->message($espace, $autre, ['expire_le' => now()->subHour()]);

        Sanctum::actingAs($moi);

        $this->getJson('/api/me/espaces')
            ->assertOk()
            ->assertJsonPath('prives.0.non_lus', 3);

        // L'autre membre n'a jamais rien lu : tous les messages visibles des autres (le mien).
        Sanctum::actingAs($autre);
        $this->getJson('/api/me/espaces')->assertJsonPath('prives.0.non_lus', 1);
    }

    public function test_la_liste_tient_en_une_seule_requete_sql(): void
    {
        $moi = User::factory()->create();
        foreach (['A', 'B', 'C', 'D'] as $nom) {
            $espace = $this->espace('PRIVE', "Groupe {$nom}");
            $this->adherer($espace, $moi);
            $this->message($espace, User::factory()->create());
        }

        Sanctum::actingAs($moi);

        DB::enableQueryLog();
        $this->getJson('/api/me/espaces')->assertOk()->assertJsonCount(4, 'prives');
        $requetes = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'espaces'));

        $this->assertCount(1, $requetes, 'Une requête par espace (N+1) : '.$requetes->implode(' | '));
    }

    public function test_un_visiteur_non_connecte_recoit_401(): void
    {
        $this->getJson('/api/me/espaces')->assertUnauthorized();
    }

    private function espace(string $type, string $nom, array $attributs = []): Espace
    {
        return Espace::create(array_merge(['type' => $type, 'nom' => $nom, 'duree_visibilite_heures' => 168], $attributs));
    }

    private function adherer(Espace $espace, User $user, RoleMembre $role = RoleMembre::MEMBRE): void
    {
        Adhesion::create(['espace_id' => $espace->id, 'user_id' => $user->id, 'role' => $role]);
    }

    private function message(Espace $espace, User $auteur, array $attributs = []): Message
    {
        return Message::create(array_merge([
            'espace_id' => $espace->id,
            'auteur_id' => $auteur->id,
            'contenu' => 'Bonjour',
        ], $attributs));
    }
}
