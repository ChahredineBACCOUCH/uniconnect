<?php

namespace Database\Seeders;

use App\Enums\EtatCompte;
use App\Enums\EtatInvitation;
use App\Enums\Profil;
use App\Enums\RoleMembre;
use App\Enums\TypeEspace;
use App\Models\Adhesion;
use App\Models\AnneeUniversitaire;
use App\Models\Annonce;
use App\Models\AssistantIA;
use App\Models\Chapitre;
use App\Models\Classe;
use App\Models\CompteAutorise;
use App\Models\Espace;
use App\Models\Invitation;
use App\Models\Matiere;
use App\Models\Message;
use App\Models\Ressource;
use App\Models\User;
use App\Notifications\InvitationRecue;
use App\Notifications\NouvelleAnnonce;
use App\Notifications\NouvelleRessource;
use App\Services\GenerationGroupesService;
use App\Services\IndexationService;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contenu de la démonstration du 19/11 (SCRUM-188), par-dessus DemoSeeder :
 * groupes générés, cours « Bases de données » avec 3 PDF indexés pour l'Assistant IA,
 * une discussion d'une vingtaine de messages, 2 annonces, un groupe privé, un channel
 * et une invitation en attente pour l'étudiant de démo.
 *
 * Idempotent : chaque élément est retrouvé avant d'être créé (firstOrCreate…), le
 * relancer ne crée pas de doublon. En production (pas de Shell Render en offre gratuite) :
 * SEED_DEMO=true dans les variables Render, redéployer, puis retirer la variable
 * (voir backend/docker/start.sh et docs/demo.md).
 *
 * Pas de signalement ni de modération : ces fonctionnalités sont prévues en v1.1.
 */
class DemoProductionSeeder extends Seeder
{
    /** Durée de visibilité des messages du cours de démonstration : 30 jours. */
    private const VISIBILITE_COURS_HEURES = 720;

    /** Supports publiés dans le groupe « Bases de données » (database/seeders/demo). */
    private const COURS = [
        ['chapitre' => 'Chapitre 1 – Modèle relationnel', 'titre' => 'Chapitre 1 – Le modèle relationnel', 'fichier' => 'chapitre-1-modele-relationnel.pdf', 'description' => 'Relations, clés primaires et étrangères, contraintes d\'intégrité.'],
        ['chapitre' => 'Chapitre 2 – Normalisation', 'titre' => 'Chapitre 2 – Dépendances fonctionnelles et normalisation', 'fichier' => 'chapitre-2-normalisation.pdf', 'description' => '1FN, 2FN, 3FN et BCNF, avec exemples.'],
        ['chapitre' => 'Chapitre 3 – Curseurs PL/SQL', 'titre' => 'Chapitre 3 – Les curseurs PL/SQL', 'fichier' => 'chapitre-3-curseurs-plsql.pdf', 'description' => 'OPEN / FETCH / CLOSE, attributs, boucle FOR, curseurs paramétrés.'],
    ];

    /** Camarades de classe de l'étudiant de démo (comptes autorisés créés par DemoSeeder). */
    private const CAMARADES = ['ETU2026-001', 'ETU2026-004', 'ETU2026-007', 'ETU2026-010'];

    public function run(GenerationGroupesService $generation, IndexationService $indexation): void
    {
        $this->call(DemoSeeder::class);

        $etudiant = User::where('email', 'etudiant@demo.uniconnect')->firstOrFail();
        $enseignant = User::where('email', 'enseignant@demo.uniconnect')->firstOrFail();
        $admin = User::where('email', 'admin@demo.uniconnect')->firstOrFail();

        $annee = AnneeUniversitaire::where('active', true)->firstOrFail();
        $classe = Classe::where('code', 'L2-INFO-A')->where('annee_id', $annee->id)->firstOrFail();
        $bd = Matiere::where('code', 'INF202')->firstOrFail();

        // L'enseignante de démo est responsable de « Bases de données » : elle devient
        // propriétaire du groupe de la matière à la génération.
        $bd->enseignants()->syncWithoutDetaching([$enseignant->id]);

        $camarades = $this->activerCamarades($classe);

        $generation->generer($admin);

        $cours = Espace::where('type', TypeEspace::ORGANISATIONNEL->value)
            ->where('annee_id', $annee->id)
            ->where('matiere_id', $bd->id)
            ->firstOrFail();

        $this->preparerCours($cours, $enseignant, $etudiant, $camarades);
        $this->publierSupports($cours, $enseignant, $etudiant, $indexation);
        $this->publierAnnonces($cours, $enseignant, $etudiant);
        $this->remplirDiscussion($cours, $enseignant, $etudiant, $camarades);
        $this->creerGroupePrive($etudiant, $camarades);
        $this->creerChannel($admin, $etudiant, $camarades);
        $this->inviterEtudiant($enseignant, $etudiant);
    }

    /**
     * Active 4 comptes de la classe L2 Informatique A pour une discussion réaliste.
     * Mot de passe aléatoire jamais communiqué : ces comptes ne servent pas à se connecter.
     *
     * @return list<User>
     */
    private function activerCamarades(Classe $classe): array
    {
        $camarades = [];

        foreach (self::CAMARADES as $matricule) {
            $compte = CompteAutorise::where('matricule', $matricule)->firstOrFail();

            $user = User::firstOrCreate(
                ['email' => $compte->email],
                [
                    'matricule' => $compte->matricule,
                    'nom' => $compte->nom,
                    'prenom' => $compte->prenom,
                    'password' => Hash::make(Str::random(40)),
                    'profil' => Profil::ETUDIANT,
                    'classe_id' => $classe->id,
                    'etat' => EtatCompte::ACTIF,
                    'email_verified_at' => now(),
                ],
            );

            $compte->update(['classe_id' => $classe->id, 'etat' => EtatCompte::ACTIF, 'user_id' => $user->id]);

            foreach (Profil::ETUDIANT->droitsParDefaut() as $droit) {
                $user->accorderDroit($droit);
            }

            $camarades[] = $user;
        }

        return $camarades;
    }

    /**
     * Membres, durée de visibilité et Assistant IA du groupe « Bases de données ».
     *
     * @param  list<User>  $camarades
     */
    private function preparerCours(Espace $cours, User $enseignant, User $etudiant, array $camarades): void
    {
        if ($cours->duree_visibilite_heures === null) {
            $cours->update(['duree_visibilite_heures' => self::VISIBILITE_COURS_HEURES]);
        }

        // Si le groupe existait déjà (généré avant que l'enseignante soit rattachée à la
        // matière), elle en devient co-gestionnaire pour publier supports et annonces.
        $aUnProprietaire = $cours->adhesions()->where('role', RoleMembre::PROPRIETAIRE->value)->exists();
        $adhesion = Adhesion::firstOrCreate(
            ['espace_id' => $cours->id, 'user_id' => $enseignant->id],
            ['role' => $aUnProprietaire ? RoleMembre::CO_GESTIONNAIRE : RoleMembre::PROPRIETAIRE],
        );
        if ($adhesion->role === RoleMembre::MEMBRE) {
            $adhesion->update(['role' => RoleMembre::CO_GESTIONNAIRE]);
        }

        foreach ([$etudiant, ...$camarades] as $membre) {
            Adhesion::firstOrCreate(['espace_id' => $cours->id, 'user_id' => $membre->id], ['role' => RoleMembre::MEMBRE]);
        }

        AssistantIA::updateOrCreate(
            ['espace_id' => $cours->id],
            [
                'actif' => true,
                'citer_sources' => true,
                'instructions' => 'Réponds en français, simplement, avec un exemple SQL ou PL/SQL quand c\'est utile.',
            ],
        );
    }

    /**
     * Publie les 3 PDF du cours et les indexe tout de suite (sans passer par la file),
     * pour que l'Assistant IA puisse répondre dès la fin du déploiement.
     */
    private function publierSupports(Espace $cours, User $enseignant, User $etudiant, IndexationService $indexation): void
    {
        foreach (self::COURS as $ordre => $support) {
            $chapitre = Chapitre::firstOrCreate(
                ['espace_id' => $cours->id, 'titre' => $support['chapitre']],
                ['ordre' => $ordre],
            );

            $ressource = Ressource::where('chapitre_id', $chapitre->id)->where('titre', $support['titre'])->first();

            if ($ressource === null) {
                $source = database_path('seeders/demo/'.$support['fichier']);
                $chemin = Storage::putFileAs('espaces/'.$cours->id.'/ressources', new File($source), $support['fichier']);

                // Sans événements : l'observateur lancerait une indexation en file en plus
                // de celle faite ci-dessous.
                $ressource = Ressource::withoutEvents(fn () => Ressource::create([
                    'chapitre_id' => $chapitre->id,
                    'auteur_id' => $enseignant->id,
                    'titre' => $support['titre'],
                    'description' => $support['description'],
                    'chemin_fichier' => (string) $chemin,
                    'mime' => 'application/pdf',
                    'taille' => filesize($source),
                    'indexee_ia' => false,
                ]));

                $etudiant->notify(new NouvelleRessource($cours, $ressource, $enseignant));
            }

            if (! $ressource->indexee_ia || ! $ressource->fragments()->exists()) {
                $indexation->indexer($ressource);
            }
        }

        $exercices = Chapitre::where('espace_id', $cours->id)->where('titre', self::COURS[0]['chapitre'])->firstOrFail();
        Ressource::withoutEvents(fn () => Ressource::firstOrCreate(
            ['chapitre_id' => $exercices->id, 'titre' => 'Exercices SQL interactifs (SQLZoo)'],
            ['auteur_id' => $enseignant->id, 'url' => 'https://sqlzoo.net/', 'description' => 'Pour s\'entraîner aux requêtes SELECT.', 'indexee_ia' => false],
        ));
    }

    private function publierAnnonces(Espace $cours, User $enseignant, User $etudiant): void
    {
        $annonces = [
            ['titre' => 'Supports du cours en ligne', 'contenu' => "Les trois chapitres du cours sont disponibles dans l'onglet Ressources.\nL'Assistant IA du cours répond à vos questions à partir de ces supports, en citant les pages."],
            ['titre' => 'TP noté jeudi 20/11 – curseurs PL/SQL', 'contenu' => "Le TP noté aura lieu jeudi 20/11 de 10 h à 12 h en salle B12.\nIl porte sur le chapitre 3 : curseurs explicites, attributs et boucle FOR.\nDocuments autorisés : vos notes personnelles."],
        ];

        foreach ($annonces as $donnees) {
            $annonce = Annonce::firstOrCreate(
                ['espace_id' => $cours->id, 'titre' => $donnees['titre']],
                ['auteur_id' => $enseignant->id, 'contenu' => $donnees['contenu'], 'notifier_email' => false],
            );

            if ($annonce->wasRecentlyCreated) {
                $etudiant->notify(new NouvelleAnnonce($annonce));
            }
        }
    }

    /**
     * Une vingtaine de messages répartis sur les derniers jours, dont une fiche de
     * révision en pièce jointe.
     *
     * @param  list<User>  $camarades
     */
    private function remplirDiscussion(Espace $cours, User $enseignant, User $etudiant, array $camarades): void
    {
        [$yasmine, $karim, $rania, $hamza] = $camarades;

        $fil = [
            [$enseignant, 3, 'Bonjour à tous ! Bienvenue dans le groupe du cours de Bases de données. Les supports sont dans l\'onglet Ressources.'],
            [$yasmine, 3, 'Merci madame ! Le cours de mardi portait bien sur la 3FN ?'],
            [$enseignant, 3, 'Oui : dépendances transitives et décomposition. Relisez le chapitre 2, page 3.'],
            [$karim, 3, 'Je n\'ai pas compris la différence entre 3FN et BCNF.'],
            [$enseignant, 3, 'En BCNF, toute dépendance X -> Y doit avoir une clé candidate à gauche. La 3FN tolère une exception quand Y fait partie d\'une clé.'],
            [$etudiant, 3, 'Merci, c\'est plus clair. On aura un exercice de normalisation à l\'examen ?'],
            [$enseignant, 2, 'Probablement, comme chaque année : une relation à décomposer jusqu\'en 3FN.'],
            [$rania, 2, 'Est-ce que quelqu\'un a la correction de l\'exercice 4 du TD ?'],
            [$hamza, 2, 'Pas encore, on peut la faire ensemble demain à la bibliothèque.'],
            [$rania, 2, 'Bonne idée, 14 h ?'],
            [$hamza, 2, 'Ça marche !'],
            [$enseignant, 1, 'Le chapitre 3 sur les curseurs PL/SQL est en ligne. Le TP noté de jeudi porte dessus.'],
            [$karim, 1, 'Le TP porte aussi sur les curseurs paramétrés ?'],
            [$enseignant, 1, 'Oui : la boucle FOR et les curseurs paramétrés (pages 3 et 4 du chapitre 3).'],
            [$etudiant, 1, 'Il faut toujours fermer un curseur avec CLOSE ?'],
            [$enseignant, 1, 'Oui pour un curseur ouvert avec OPEN. Avec une boucle FOR, la fermeture est automatique.'],
            [$yasmine, 1, 'Je partage ma fiche de révision sur la normalisation, si ça peut aider.'],
            [$karim, 0, 'Merci Yasmine, elle est très claire !'],
            [$etudiant, 0, 'Merci ! Je vais aussi poser mes questions à l\'Assistant IA du cours.'],
            [$enseignant, 0, 'Bonne idée, et vérifiez toujours ses réponses dans les pages citées. Bon courage pour jeudi !'],
        ];

        foreach ($fil as $rang => [$auteur, $joursAvant, $contenu]) {
            $envoye = now()->subDays($joursAvant)->setTime(9 + intdiv($rang, 2) % 9, ($rang * 7) % 60);

            $message = Message::firstOrCreate(
                ['espace_id' => $cours->id, 'auteur_id' => $auteur->id, 'contenu' => $contenu],
                ['expire_le' => $envoye->copy()->addHours(self::VISIBILITE_COURS_HEURES)],
            );

            if ($message->wasRecentlyCreated) {
                $message->forceFill(['created_at' => $envoye, 'updated_at' => $envoye])->saveQuietly();
            }

            if ($message->wasRecentlyCreated && str_contains($contenu, 'fiche de révision')) {
                $source = database_path('seeders/demo/chapitre-2-normalisation.pdf');
                $chemin = Storage::putFileAs('espaces/'.$cours->id, new File($source), Str::uuid().'.pdf');

                $message->piecesJointes()->create([
                    'nom' => 'fiche-revision-normalisation.pdf',
                    'chemin' => (string) $chemin,
                    'mime' => 'application/pdf',
                    'taille_octets' => filesize($source),
                    'expire_le' => $message->expire_le,
                ]);
            }
        }
    }

    /** @param  list<User>  $camarades */
    private function creerGroupePrive(User $etudiant, array $camarades): void
    {
        $groupe = Espace::firstOrCreate(
            ['type' => TypeEspace::PRIVE->value, 'nom' => 'Projet intégration – Équipe 4', 'created_by' => $etudiant->id],
            ['description' => 'Organisation du projet d\'intégration (L2 Informatique A).', 'duree_visibilite_heures' => 168],
        );

        Adhesion::firstOrCreate(['espace_id' => $groupe->id, 'user_id' => $etudiant->id], ['role' => RoleMembre::PROPRIETAIRE]);
        foreach ([$camarades[0], $camarades[1]] as $membre) {
            Adhesion::firstOrCreate(['espace_id' => $groupe->id, 'user_id' => $membre->id], ['role' => RoleMembre::MEMBRE]);
        }

        foreach ([
            [$camarades[0], 'On fait le point sur le projet vendredi ?'],
            [$etudiant, 'Oui, je prépare la maquette des écrans d\'ici là.'],
            [$camarades[1], 'Je m\'occupe du schéma de la base.'],
        ] as [$auteur, $contenu]) {
            Message::firstOrCreate(
                ['espace_id' => $groupe->id, 'auteur_id' => $auteur->id, 'contenu' => $contenu],
                ['expire_le' => now()->addHours(168)],
            );
        }
    }

    /** @param  list<User>  $camarades */
    private function creerChannel(User $admin, User $etudiant, array $camarades): void
    {
        $channel = Espace::firstOrCreate(
            ['type' => TypeEspace::CHANNEL->value, 'nom' => 'Club Développement web'],
            [
                'description' => 'Partage de ressources, ateliers et hackathons autour du développement web.',
                'mode_acces' => 'OUVERT',
                'duree_visibilite_heures' => 720,
                'created_by' => $admin->id,
            ],
        );

        Adhesion::firstOrCreate(['espace_id' => $channel->id, 'user_id' => $admin->id], ['role' => RoleMembre::PROPRIETAIRE]);
        foreach ([$etudiant, $camarades[2], $camarades[3]] as $membre) {
            Adhesion::firstOrCreate(['espace_id' => $channel->id, 'user_id' => $membre->id], ['role' => RoleMembre::MEMBRE]);
        }

        Message::firstOrCreate(
            ['espace_id' => $channel->id, 'auteur_id' => $admin->id, 'contenu' => 'Bienvenue dans le club ! Premier atelier Vue.js mercredi à 14 h en salle C03.'],
            ['expire_le' => now()->addHours(720)],
        );
    }

    /** Invitation en attente : l'étudiant de démo peut l'accepter pendant la présentation. */
    private function inviterEtudiant(User $enseignant, User $etudiant): void
    {
        $groupe = Espace::firstOrCreate(
            ['type' => TypeEspace::PRIVE->value, 'nom' => 'Révisions BD – soutien', 'created_by' => $enseignant->id],
            ['description' => 'Séances de soutien avant le TP noté.', 'duree_visibilite_heures' => 168],
        );

        Adhesion::firstOrCreate(['espace_id' => $groupe->id, 'user_id' => $enseignant->id], ['role' => RoleMembre::PROPRIETAIRE]);

        if (Adhesion::where('espace_id', $groupe->id)->where('user_id', $etudiant->id)->exists()) {
            return;
        }

        $invitation = Invitation::firstOrCreate(
            ['espace_id' => $groupe->id, 'invite_id' => $etudiant->id, 'etat' => EtatInvitation::EN_ATTENTE->value],
            ['invite_par_id' => $enseignant->id, 'expire_le' => now()->addDays(14)],
        );

        if ($invitation->wasRecentlyCreated) {
            $etudiant->notify(new InvitationRecue($groupe, $enseignant));
        }
    }
}
