# Présenter le code au jury, ligne par ligne

Quand le jury demande « comment ça fonctionne ? », ouvrir le code et le dérouler avec la même méthode à chaque fois. Ce guide donne, pour chaque fonctionnalité de la démo : **quoi ouvrir**, **le code**, et **quoi dire, bloc par bloc**.

Vue d'ensemble (fichiers et lignes de toutes les étapes) : [demo-code.md](demo-code.md).

## Avant la présentation

- [ ] VS Code ouvert sur le dépôt, **zoom à 150 %** (`Ctrl` + `=`), thème clair (lisible au projecteur).
- [ ] Barre latérale fermée (`Ctrl` + `B`) : le code prend tout l'écran.
- [ ] Onglets déjà ouverts, dans l'ordre de la démo : `routes/api.php`, `EligibiliteService.php`, `ActivationController.php`, `FichierPolicy.php`, `MessageController.php`, `IndexationService.php`, `AssistantService.php`, `ClientIAAvecSecours.php`.
- [ ] Un terminal dans `backend/`, pour lancer un test en direct (voir « La preuve » dans chaque fiche).

| Raccourci VS Code | Usage |
|---|---|
| `Ctrl` + `P`, puis le nom du fichier | Ouvrir un fichier |
| `Ctrl` + `G`, puis le numéro | Aller à une ligne |
| `Ctrl` + `Shift` + `O` | Aller à une fonction du fichier |
| `F12` sur un nom | Ouvrir la définition (service, policy…) |
| `Alt` + `←` | Revenir en arrière |

## La méthode : toujours les 4 mêmes temps

1. **La route** (`backend/routes/api.php`) : « l'écran appelle cette URL ; voici sa protection (connexion, droit, limite d'essais) ».
2. **Le contrôleur** : « il reçoit la requête, vérifie le droit, valide les données ».
3. **La règle métier** (service, policy, migration) : « voici la ligne qui décide ».
4. **La preuve** : « voici le test automatique qui vérifie ce cas ; il passe en CI à chaque PR ».

Règles d'oral :
- Lire le **nom** de la fonction et le **commentaire** au-dessus : ils sont écrits pour être compris.
- Ne pas lire chaque mot ; **montrer la ligne qui décide** et dire ce qu'elle garantit.
- Finir par la phrase de sécurité : « et c'est vérifié côté serveur, pas seulement dans l'interface ».

---

## Fiche 1 · « Comment garantissez-vous une seule année active ? »

**Ouvrir** : `backend/app/Http/Controllers/Admin/AnneeController.php`, ligne **137** (`activer`).

```php
DB::transaction(function () use ($annee, $donnees): void {           // ①
    $precedente = AnneeUniversitaire::query()
        ->where('active', true)
        ->where('id', '!=', $annee->getKey())
        ->lockForUpdate()                                            // ②
        ->first();

    AnneeUniversitaire::query()->where('active', true)
        ->update(['active' => false]);                               // ③
    $annee->update(['active' => true]);

    if (($donnees['archiver_precedente'] ?? false) && $precedente !== null) {
        Espace::query()->where('annee_id', $precedente->getKey())
            ->update(['etat' => EtatEspace::ARCHIVE->value]);        // ④
    }
});
```

**Dire** :
- ① « Tout se passe dans une **transaction** : soit tout réussit, soit rien n'est modifié. »
- ② « `lockForUpdate` **verrouille** la ligne de l'année active, pour que deux administrateurs ne puissent pas activer deux années en même temps. »
- ③ « On désactive d'abord toutes les années, puis on active la nouvelle. »
- ④ « En option, les groupes de l'année précédente passent en archive. »

**Puis ouvrir** la migration `2026_11_06_214553_create_annees_universitaires_table.php`, ligne **30** :

```php
DB::statement('CREATE UNIQUE INDEX annee_une_seule_active ON annees_universitaires (active) WHERE active');
```

« C'est un **index unique partiel** : il ne porte que sur les lignes où `active` est vrai, donc PostgreSQL refuse physiquement une deuxième année active. Même un bug dans le code ne pourrait pas casser la règle. »

**La preuve** : `php artisan test --filter=test_activer_une_annee_desactive_l_ancienne_et_il_n_en_reste_qu_une_active`

---

## Fiche 2 · « Comment empêchez-vous n'importe qui de s'inscrire ? »

**Ouvrir** : `routes/api.php` (chercher `auth/register`), puis `backend/app/Services/EligibiliteService.php`, ligne **32**.

```php
Route::post('/auth/register', [RegisterController::class, 'store'])
    ->middleware('throttle:10,1');                                   // ①
```

```php
public function verifier(string $matricule, string $email): ?string
{
    if (! AnneeUniversitaire::active()->exists()) {
        return self::PLATEFORME_FERMEE;                              // ②
    }

    $figureSurLaListe = CompteAutorise::query()
        ->where('matricule', $matricule)
        ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))]) // ③
        ->exists();

    if (! $figureSurLaListe) {
        return self::NON_AUTORISE;                                   // ④
    }

    if (User::query()->where('matricule', $matricule)->exists()) {
        return self::DEJA_INSCRIT;                                   // ⑤
    }

    return null;                                                     // ⑥
}
```

**Dire** :
- ① « La route est limitée à **10 essais par minute et par IP** : on ne peut pas tester en masse qui est inscrit à l'université. »
- ② « Premier contrôle : sans année universitaire active, la plateforme est fermée. »
- ③ « Deuxième contrôle : le **couple** matricule + e-mail doit figurer dans la liste fournie par la scolarité. L'e-mail est comparé en minuscules : sur téléphone, la première lettre est souvent mise en majuscule automatiquement. »
- ④ « C'est ce refus qu'on a vu avec `ETU2026-999`. »
- ⑤ « Troisième contrôle : un seul compte par matricule. »
- ⑥ « `null` signifie "éligible" : le contrôleur crée alors le compte en attente et envoie l'e-mail. »

**La preuve** : `php artisan test --filter=test_un_matricule_absent_de_la_liste_des_comptes_autorises_est_refuse`

---

## Fiche 3 · « Comment le lien d'activation est-il sécurisé ? »

**Ouvrir** : `backend/app/Http/Middleware/VerifierLienActivation.php`, ligne **25**.

```php
$attendu = Request::create(URL::temporarySignedRoute(                // ①
    'activation',
    Carbon::createFromTimestamp((int) $expires),
    ['user' => $identifiant],
));

abort_unless(hash_equals((string) $attendu->query('signature'), $signature), 403); // ②

abort_unless(URL::hasValidSignature($attendu), 403);                 // ③
```

**Dire** :
- « Le lien reçu par e-mail contient l'identifiant du compte, une date d'expiration et une **signature**. »
- ① « Le serveur recalcule la signature attendue avec la **clé secrète** de l'application (HMAC). Sans cette clé, impossible de fabriquer un lien valide ou de modifier l'identifiant. »
- ② « `hash_equals` compare les deux signatures **en temps constant**, ce qui évite de deviner la signature en mesurant le temps de réponse. »
- ③ « Puis on vérifie que les **48 heures** ne sont pas dépassées. »

**Puis** `backend/app/Http/Controllers/ActivationController.php`, ligne **31** :

```php
abort_unless($compte->etat === EtatCompte::EN_ATTENTE, 403, 'Ce compte a déjà été activé.'); // ④

$compte->forceFill([
    'password' => Hash::make($donnees['password']),                 // ⑤
    'etat' => EtatCompte::ACTIF,
    'email_verified_at' => now(),
])->save();

foreach ($compte->profil->droitsParDefaut() as $droit) {
    $compte->accorderDroit($droit);                                  // ⑥
}
$rattachement->rattacher($compte);                                   // ⑦

$token = $compte->createToken('activation')->plainTextToken;         // ⑧
```

- ④ « Le lien ne sert qu'**une fois** : un compte déjà actif est refusé. »
- ⑤ « Le mot de passe est **haché** avec bcrypt : il n'est jamais stocké en clair, même nous ne pouvons pas le lire. »
- ⑥ « On donne les droits par défaut du profil, par exemple créer un groupe privé. »
- ⑦ « On **rattache automatiquement** l'étudiant au groupe de sa classe et de ses matières. C'est pour ça qu'il arrive déjà membre. »
- ⑧ « On renvoie un **jeton Sanctum** : la personne est connectée directement. »

**La preuve** : `php artisan test --filter=test_un_lien_expire_est_refuse`

---

## Fiche 4 · « Et si quelqu'un essaie de deviner un mot de passe ? »

**Ouvrir** : `backend/app/Http/Controllers/Auth/LoginController.php` : `store` ligne **31**, `enregistrerEchec` ligne **106**.

```php
// Compte inconnu : même message qu'un mot de passe faux, on ne révèle rien.
if (! $compte) {
    return $this->identifiantsIncorrects();                          // ①
}
if ($compte->bloque_jusqua && $compte->bloque_jusqua->isFuture()) {
    return response()->json([...], 423);                             // ②
}
if (! Hash::check($donnees['password'], $compte->password)) {
    return $this->enregistrerEchec($compte);                         // ③
}
```

```php
$tentatives = $compte->tentatives_echouees + 1;
if ($tentatives >= self::TENTATIVES_MAX) {                           // ④
    $compte->forceFill([
        'tentatives_echouees' => 0,
        'bloque_jusqua' => now()->addMinutes(self::DUREE_BLOCAGE_MINUTES),
    ])->save();
    return response()->json([...], 423);
}
```

**Dire** :
- ① « Compte inconnu ou mot de passe faux : **même message**, pour ne pas révéler quelles adresses existent. »
- ② « Un compte bloqué reçoit le code 423 et l'heure de déblocage. »
- ③ « `Hash::check` compare le mot de passe saisi avec le hachage, sans jamais le déchiffrer. »
- ④ « Au bout de **5 échecs**, le compte est bloqué **15 minutes**. »

**La preuve** : `php artisan test --filter=test_cinq_echecs_bloquent_le_compte_pendant_quinze_minutes`

---

## Fiche 5 · « Comment fonctionne l'import Excel ? »

**Ouvrir** : `backend/app/Services/ImportComptesService.php` : `lire` ligne **116**, `analyser` ligne **196**, `valider` ligne **67**.

Dérouler à l'oral, sans tout lire (la fonction est longue) :

1. « `lire` ouvre le fichier avec la bibliothèque `maatwebsite/excel` (`Excel::toArray`, ligne 119) et vérifie les colonnes attendues. »
2. « `analyser` parcourt les lignes une par une. Avant la boucle, **deux requêtes seulement** chargent les matricules et e-mails déjà en base (lignes 205-216) : on ne fait pas une requête par ligne. »
3. Montrer le contrôle du matricule :

   ```php
   } elseif (isset($matriculesVus[$matricule])) {
       $motifs[] = 'Ce matricule figure plusieurs fois dans le fichier.';                 // doublon interne
   } elseif (isset($matriculesExistants[$matricule])) {
       $motifs[] = 'Ce matricule figure déjà dans la liste des comptes autorisés.';      // doublon en base
   }
   ```

   « Chaque erreur est expliquée avec son numéro de ligne : c'est l'aperçu « 3 valides, 1 doublon, 2 erreurs ». **Rien n'est encore écrit en base.** »
4. « `valider` n'insère que les lignes valides, dans **une transaction** (ligne 80), et garde une trace de l'import. »

**La preuve** : `php artisan test --filter=test_valider_deux_fois_le_meme_fichier_ne_cree_pas_de_doublon`

---

## Fiche 6 · « Comment les groupes sont-ils créés automatiquement ? »

**Ouvrir** : `backend/app/Services/GenerationGroupesService.php` : `plan` ligne **176**, `generer` ligne **83**.

```php
foreach ($this->plan($annee, $auteur) as $groupe) {                  // ①
    $espace = Espace::firstOrCreate(                                 // ②
        ['type' => TypeEspace::ORGANISATIONNEL->value, 'annee_id' => $annee->id,
         'classe_id' => $groupe['classe']?->id, 'matiere_id' => $groupe['matiere']?->id],
        ['nom' => $groupe['nom'], 'description' => $groupe['description'], ...],
    );
    $membresAjoutes += $this->ajouterMembres(                        // ③
        $espace, $groupe['proprietaire'], $groupe['etudiants'],
    );
}
```

**Dire** :
- ① « `plan` calcule la liste à partir des référentiels : **un groupe par classe** (propriétaire : l'administration) et **un groupe par matière** (propriétaire : l'enseignant responsable), avec les étudiants actifs. »
- ② « `firstOrCreate` : si le groupe existe déjà, on le réutilise. Avec l'index unique `espace_orga_unique` (migration des espaces, ligne 45), **relancer la génération ne crée aucun doublon**. »
- ③ « Les membres sont ajoutés de la même façon, sans doublon. Le tout est dans une transaction (ligne 87). »

**La preuve** : `php artisan test --filter=test_la_generation_ne_cree_jamais_de_doublon`

---

## Fiche 7 · « Comment marche l'expiration des messages ? »

**Ouvrir** : `backend/app/Http/Controllers/MessageController.php` : `store` ligne **79**, `calculerExpiration` ligne **169**.

```php
$this->authorize('view', $espace);                                   // ①

$message = Message::create([
    'espace_id' => $espace->id,
    'auteur_id' => $request->user()->id,
    'contenu' => (string) $request->validated('contenu'),
    'expire_le' => $this->calculerExpiration(                        // ②
        $espace, $request->validated('duree_visibilite_heures'),
    ),
]);

foreach ($request->file('fichiers', []) as $fichier) {
    $chemin = Storage::putFileAs('espaces/'.$espace->id, $fichier,
        Str::uuid().'.'.$fichier->extension());                      // ③
    $message->piecesJointes()->create([
        ...
        'expire_le' => $message->expire_le,                          // ④
    ]);
}
```

```php
private function calculerExpiration(Espace $espace, ?int $dureeChoisie): ?Carbon
{
    $duree = $dureeChoisie ?? $espace->duree_visibilite_heures;
    if ($duree === null) {
        return null;                                                 // ⑤
    }
    return now()->addHours($duree);
}
```

**Dire** :
- ① « Seul un **membre** peut écrire (policy `view`). »
- ② « La date d'expiration est **calculée et figée à l'envoi**. Si le groupe change sa règle plus tard, les anciens messages ne changent pas : ce n'est pas rétroactif. »
- ③ « Le fichier est stocké sous un **nom aléatoire** (UUID) : on ne peut pas deviner son adresse. »
- ④ « La pièce jointe **expire en même temps** que son message. »
- ⑤ « `null` signifie une conservation illimitée. »
- « Ensuite, toutes les lectures filtrent `expire_le > maintenant` (lignes 50-52) : un message expiré disparaît partout. »

**La preuve** : `php artisan test --filter=test_un_message_expire_apres_la_duree_de_visibilite_de_sept_jours`

---

## Fiche 8 · « Un non-membre peut-il télécharger un fichier avec le lien direct ? »

**Ouvrir** : `routes/api.php` (chercher `fichiers/{pj}`), puis `backend/app/Http/Controllers/FichierController.php` ligne **18**, puis `backend/app/Policies/FichierPolicy.php` ligne **20**.

```php
public function telecharger(Request $request, PieceJointe $pj): StreamedResponse
{
    $this->authorize('view', $pj);                                   // ①
```

```php
public function view(User $user, PieceJointe $fichier): bool
{
    // Adhésion contrôlée en base, jamais depuis le jeton : le retrait prend
    // effet dès la requête suivante.
    if (! $espace->adhesions()->where('user_id', $user->id)->exists()) {
        return false;                                                // ②
    }
    if ($fichier->expire_le !== null && $fichier->expire_le->isPast()) {
        return false;                                                // ③
    }
    $message = $fichier->message;
    if ($message instanceof Message) {
        return ! $message->masque
            && ($message->expire_le === null || $message->expire_le->isFuture()); // ④
    }
    return $fichier->annonce !== null;
}
```

**Dire** :
- « Le stockage (Supabase) est **privé** : aucun fichier n'a d'adresse publique. Tout téléchargement passe par cette route de l'API. »
- ① « Avant d'envoyer le moindre octet, le contrôleur demande à la **policy** si c'est autorisé. Sinon : 403. »
- ② « On vérifie l'adhésion **dans la base, à chaque requête**, pas dans le jeton. Si l'enseignant retire quelqu'un du groupe, il perd l'accès **immédiatement**, même s'il a gardé le lien. »
- ③ « Un fichier expiré n'est plus téléchargeable. »
- ④ « Si le message a été masqué ou a expiré, son fichier non plus. »

**La preuve** : `php artisan test --filter=test_un_membre_retire_ne_peut_plus_telecharger_les_fichiers`

---

## Fiche 9 · « Comment l'IA peut-elle lire un PDF ? »

**Ouvrir** : `backend/app/Observers/RessourceObserver.php` ligne **33**, puis `backend/app/Services/IndexationService.php` : `indexer` ligne **28**, `decouper` ligne **116**.

```php
IndexerRessource::dispatch($ressource)->afterCommit();               // ①
```

```php
$fragments = $this->decouper($this->extrairePages($ressource));      // ②

DB::transaction(function () use ($ressource, $fragments) {
    $ressource->fragments()->delete();                               // ③
    foreach (array_chunk($fragments, 100) as $lot) {
        FragmentDocument::insert(...);                               // ④
    }
    $ressource->forceFill(['indexee_ia' => $fragments !== []])->saveQuietly(); // ⑤
});
```

Dans `decouper` :

```php
$fin = min($debut + self::TAILLE_FRAGMENT, $longueur);               // ⑥ 1 500 caractères
// Recule jusqu'à l'espace le plus proche, sans descendre sous la moitié du fragment.
$espace = mb_strrpos(mb_substr($texte, $debut, $fin - $debut), ' ');
...
$reprise = $fin - self::CHEVAUCHEMENT;                               // ⑦ 200 caractères
```

**Dire** :
- ① « Quand l'enseignant ajoute un PDF, un **observer** lance un **job en file d'attente**, après l'enregistrement en base. L'enseignant n'attend pas : l'indexation tourne en arrière-plan. »
- ② « On extrait le texte **page par page** (bibliothèque `smalot/pdfparser`), puis on le découpe. »
- ③ « Si le PDF est remplacé, on supprime d'abord les anciens fragments. »
- ④ « On insère par lots de 100, ce qui fait peu de requêtes. »
- ⑤ « `indexee_ia` passe à vrai, d'où le badge "Indexé pour l'IA". »
- ⑥ « Fragments d'environ **1 500 caractères**, coupés sur une espace pour ne pas couper un mot. »
- ⑦ « Chaque fragment reprend les **200 derniers caractères** du précédent, pour ne pas couper une idée en deux. Chaque fragment garde son **numéro de page** : c'est ce qui permet de citer "p. 3". »
- « Dans PostgreSQL, chaque fragment a une colonne `tsvector` en français, avec un index **GIN** (migration des fragments, lignes 30-31) : c'est un index de recherche plein texte, comme un moteur de recherche. »

**La preuve** : `php artisan test --filter=test_ajouter_un_pdf_declenche_l_indexation_ia`

---

## Fiche 10 · « Comment l'Assistant répond-il, et comment l'empêchez-vous d'inventer ? »

**Ouvrir** dans l'ordre : `backend/app/Http/Controllers/AssistantIAController.php` ligne **35**, puis `backend/app/Services/AssistantService.php` lignes **23**, **29**, **89**, **164**.

**a. Le contrôleur : droits et quota**

```php
$this->authorize('view', $espace);                                   // ①
abort_unless((bool) $espace->assistant?->actif, 404, "L'assistant IA n'est pas activé dans cet espace.");
$quota = (int) config('uniconnect.ia.quota_questions_jour');         // ② 20
$restant = $quota - $this->questionsDuJour($user);
if ($restant <= 0) {
    return response()->json([...], 429, ['Retry-After' => ...]);
}
```

- ① « Il faut être membre de l'espace, et que l'enseignant ait activé l'assistant. »
- ② « **20 questions par jour** et par personne, pour rester dans les quotas gratuits. Au-delà : code 429, avec l'heure à laquelle réessayer. »

**b. Le service : chercher, puis rédiger (RAG)**

```php
public function poser(Espace $espace, string $question): ResultatAssistant
{
    $extraits = $this->chercher($espace, $question);                 // ③
    if ($extraits === []) {
        return new ResultatAssistant(StatutEchangeIa::SANS_SOURCE, ...); // ④
    }
    $reponse = $this->ia->repondre($this->promptSysteme($espace),
                                   $this->message($extraits, $question)); // ⑤
    if (str_contains($reponse->texte, self::REPONSE_INCONNUE)) {
        return new ResultatAssistant(StatutEchangeIa::SANS_SOURCE, ...); // ⑥
    }
    return new ResultatAssistant(StatutEchangeIa::OK, $reponse->texte,
        $this->sourcesCitees($reponse->texte, $extraits), ...);      // ⑦
}
```

- « C'est la technique **RAG** : on ne demande pas à l'IA ce qu'elle sait, on lui donne **les passages du cours** et on lui demande de répondre avec. »
- ③ « D'abord on **cherche** dans le cours de cet espace. »
- ④ « Aucun passage pertinent : on **n'appelle même pas l'IA**. On répond que le cours n'en parle pas. Pas d'appel, donc pas d'invention et pas de quota consommé. C'est le cas de la question sur la Coupe du monde. »
- ⑤ « Sinon on envoie la consigne, les extraits numérotés et la question. »
- ⑥ « Si l'IA répond le mot-clé convenu `JE_NE_SAIS_PAS`, on affiche aussi "le cours n'en parle pas". »
- ⑦ « Sinon on récupère les **sources réellement citées**. »

**c. La consigne (ligne 23)**

```php
"Tu es l'assistant du cours. Réponds uniquement à partir des extraits numérotés fournis, en français. "
."Après chaque information, cite le numéro de l'extrait entre crochets, par exemple [2]. "
."Si la réponse n'y est pas, réponds exactement JE_NE_SAIS_PAS."
```

« Trois règles : uniquement les extraits, **citer** chaque information, et un mot-clé précis quand la réponse n'y est pas. La consigne de l'enseignant (Paramètres) est ajoutée à la suite. »

**d. La recherche (ligne 89)**

```sql
WITH q AS (
    SELECT NULLIF(replace(websearch_to_tsquery('french', ?)::text, ' & ', ' | '), '')::tsquery AS requete
)
SELECT f.id, f.ressource_id, f.page, f.contenu, r.titre,
       ts_rank(f.recherche, q.requete) AS score
FROM fragments_document f
JOIN ressources r ON r.id = f.ressource_id
JOIN chapitres c ON c.id = r.chapitre_id
CROSS JOIN q
WHERE c.espace_id = ? AND f.recherche @@ q.requete
ORDER BY score DESC, f.id
LIMIT ?
```

- « `websearch_to_tsquery('french', …)` transforme la question en mots-clés **en français**. Il retire les mots vides comme "le" ou "de", et ramène les mots à leur racine : "curseurs" et "curseur" correspondent. »
- « On remplace les ET par des OU (`' & '` → `' | '`) : un passage qui contient seulement une partie des mots reste candidat. »
- « `WHERE c.espace_id = ?` : on ne cherche **que dans le cours de cet espace**. Un étudiant ne peut pas récupérer le contenu d'un autre cours. »
- « `ts_rank` classe les passages et `LIMIT` garde les **6 meilleurs**. La requête utilise l'index GIN, elle reste rapide. »

**e. Les citations (ligne 164)**

```php
preg_match_all('/[\[【](\d+(?:\s*,\s*\d+)*)(?:†[^\]】]*)?[\]】]/u', $texte, $m);
...
if ($n >= 1 && $n <= count($extraits)) {
    $numeros[$n] = true;
}
```

« On relit la réponse et on extrait les numéros entre crochets. On **ne garde que les numéros qui existent vraiment** : si l'IA citait un [9] inventé, il serait ignoré. Chaque source garde son fichier et sa page, d'où "[1] Chapitre 3 · p. 3", cliquable. »

**La preuve** : `php artisan test --filter=AssistantServiceTest`. Elle couvre :
- `test_sans_extrait_pertinent_l_ia_n_est_pas_appelee` ;
- `test_je_ne_sais_pas_donne_sans_source` ;
- `test_seules_les_ressources_de_l_espace_sont_utilisees`.

Dans les tests, l'IA est remplacée par un **faux client** : aucun appel réel.

---

## Fiche 11 · « Et si Gemini tombe en panne ? »

**Ouvrir** : `backend/app/Providers/AppServiceProvider.php` ligne **23**, puis `backend/app/IA/ClientIAAvecSecours.php` ligne **20**.

```php
$this->app->bind(ClientIA::class, ClientIAAvecSecours::class);       // ①
```

```php
public function repondre(string $systeme, string $message): ReponseIA
{
    try {
        return $this->principal->repondre($systeme, $message);       // ② Gemini
    } catch (ErreurFournisseurIA $e) {
        Log::info('Assistant IA : bascule vers Groq', [...]);        // ③
    }
    try {
        return $this->secours->repondre($systeme, $message);         // ④ Groq
    } catch (ErreurFournisseurIA $e) {
        throw new IAIndisponibleException("L'assistant est momentanément indisponible.", 0, $e); // ⑤
    }
}
```

**Dire** :
- ① « Le service ne connaît qu'une **interface** `ClientIA`. On choisit ici l'implémentation : c'est l'**injection de dépendances** de Laravel. Dans les tests, on branche un faux client à la place. »
- ② « On essaie d'abord **Gemini**. »
- ③ « En cas d'erreur (quota dépassé, panne, délai), on l'écrit dans les logs… »
- ④ « …et on **bascule sur Groq**, sans que l'étudiant le voie. »
- ⑤ « Si les deux échouent, l'étudiant reçoit un message clair (code 503), et la question ne compte pas dans son quota. »

**La preuve** : `php artisan test --filter=test_client_ia_est_gemini_puis_groq`

---

## Si le jury demande un fichier qui n'est pas ici

1. `Ctrl` + `P`, puis `api.php` : chercher l'URL concernée (`Ctrl` + `F`).
2. `F12` sur le nom du contrôleur pour l'ouvrir, puis dérouler les **4 temps** : route, contrôleur, règle, preuve.
3. Les tests portent des noms en français qui décrivent le cas : `Ctrl` + `Shift` + `F` dans `backend/tests` avec un mot-clé (« expire », « non_membre », « quota »…).
