<?php

namespace App\Services;

use App\Models\FragmentDocument;
use App\Models\Ressource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Découpe une ressource (PDF ou texte) en fragments pour l'Assistant IA (SCRUM-98).
 * Reprend l'extraction de la commande ia:poc (SCRUM-44), avec un chevauchement entre
 * fragments pour ne pas couper une idée en deux.
 */
class IndexationService
{
    public const TAILLE_FRAGMENT = 1500;   // caractères environ par fragment

    public const CHEVAUCHEMENT = 200;      // caractères repris au début du fragment suivant

    /**
     * Remplace les fragments de la ressource. Renvoie le nombre de fragments créés.
     * Un fichier illisible ne lève pas d'exception : il est journalisé et indexee_ia reste false.
     */
    public function indexer(Ressource $ressource): int
    {
        if (! $this->estIndexable($ressource)) {
            return 0;
        }

        try {
            $fragments = $this->decouper($this->extrairePages($ressource));
        } catch (Throwable $e) {
            Log::warning('Indexation IA impossible : fichier illisible', [
                'ressource_id' => $ressource->id,
                'fichier' => $ressource->chemin_fichier,
                'erreur' => $e->getMessage(),
            ]);
            $ressource->forceFill(['indexee_ia' => false])->saveQuietly();

            return 0;
        }

        DB::transaction(function () use ($ressource, $fragments) {
            $ressource->fragments()->delete();

            $maintenant = now();
            foreach (array_chunk($fragments, 100) as $lot) {
                FragmentDocument::insert(array_map(fn (array $f) => [
                    'ressource_id' => $ressource->id,
                    'page' => $f['page'],
                    'ordre' => $f['ordre'],
                    'contenu' => $f['contenu'],
                    'created_at' => $maintenant,
                ], $lot));
            }

            // saveQuietly : ne relance pas l'observer (et donc pas une nouvelle indexation).
            $ressource->forceFill(['indexee_ia' => $fragments !== []])->saveQuietly();
        });

        return count($fragments);
    }

    /** Seuls les fichiers PDF et texte sont indexés (les liens ne le sont pas). */
    public function estIndexable(Ressource $ressource): bool
    {
        if (! $ressource->chemin_fichier) {
            return false;
        }

        $extension = strtolower(pathinfo($ressource->chemin_fichier, PATHINFO_EXTENSION));

        return in_array($ressource->mime, ['application/pdf', 'text/plain'], true)
            || in_array($extension, ['pdf', 'txt'], true);
    }

    /**
     * @return array<int, string> texte nettoyé de chaque page, indexé à partir de 1
     *                            (un fichier texte est une seule « page » sans numéro : clé 0)
     */
    public function extrairePages(Ressource $ressource): array
    {
        $contenu = Storage::get($ressource->chemin_fichier);
        if ($contenu === null) {
            throw new \RuntimeException("Fichier introuvable : {$ressource->chemin_fichier}");
        }

        $estTexte = $ressource->mime === 'text/plain'
            || strtolower(pathinfo($ressource->chemin_fichier, PATHINFO_EXTENSION)) === 'txt';
        if ($estTexte) {
            return [0 => $this->nettoyer($contenu)];
        }

        $pages = [];
        foreach ((new Parser)->parseContent($contenu)->getPages() as $index => $page) {
            $texte = $this->nettoyer($page->getText());
            if ($texte !== '') {
                $pages[$index + 1] = $texte;
            }
        }

        return $pages;
    }

    /**
     * Fragments d'environ TAILLE_FRAGMENT caractères, coupés entre deux mots,
     * le suivant reprenant les CHEVAUCHEMENT derniers caractères du précédent.
     *
     * @param  array<int, string>  $pages
     * @return list<array{page: ?int, ordre: int, contenu: string}>
     */
    public function decouper(array $pages): array
    {
        $fragments = [];
        $ordre = 1;

        foreach ($pages as $numero => $texte) {
            $longueur = mb_strlen($texte);
            $debut = 0;

            while ($debut < $longueur) {
                $fin = min($debut + self::TAILLE_FRAGMENT, $longueur);
                if ($fin < $longueur) {
                    // Recule jusqu'à l'espace le plus proche, sans descendre sous la moitié du fragment.
                    $espace = mb_strrpos(mb_substr($texte, $debut, $fin - $debut), ' ');
                    if ($espace !== false && $espace > self::TAILLE_FRAGMENT / 2) {
                        $fin = $debut + $espace;
                    }
                }

                $morceau = trim(mb_substr($texte, $debut, $fin - $debut));
                if ($morceau !== '') {
                    $fragments[] = ['page' => $numero > 0 ? $numero : null, 'ordre' => $ordre++, 'contenu' => $morceau];
                }

                if ($fin >= $longueur) {
                    break;
                }
                // Repart un peu avant la fin, au début d'un mot.
                $reprise = $fin - self::CHEVAUCHEMENT;
                $espace = mb_strpos($texte, ' ', max($reprise, $debut + 1));
                $debut = ($espace !== false && $espace < $fin) ? $espace + 1 : $fin;
            }
        }

        return $fragments;
    }

    private function nettoyer(string $texte): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texte) ?? preg_replace('/\s+/', ' ', $texte));
    }
}
