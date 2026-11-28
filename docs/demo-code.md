# Démonstration : le code derrière chaque étape

Pour répondre au jury quand il demande « montrez-moi le code de cette fonctionnalité ». Chaque section suit une étape de [demo.md](demo.md) / [demo-pas-a-pas.md](demo-pas-a-pas.md) et donne :

- **où** ouvrir le code (fichier et ligne, cliquables sur GitHub) ;
- **comment** cela fonctionne, en quelques phrases ;
- **la phrase** à dire si le jury creuse.

**Pour le dérouler à l'oral, ligne par ligne** : [presenter-le-code.md](presenter-le-code.md).

Les chemins partent de la racine du dépôt. Numéros de ligne relevés sur `main` le 26/11/2026.

## 0. Architecture en une minute

```
Navigateur (Vue 3 + Vuetify, Vercel)
   │  HTTPS + jeton Sanctum (en-tête Authorization: Bearer …)
   ▼
API Laravel 13 (Docker sur Render) ── routes : backend/routes/api.php
   │  Contrôleur → (Policy : a-t-il le droit ?) → Service (règle métier) → Modèle Eloquent
   ├── PostgreSQL (Neon) : données, recherche plein texte pour l'IA
   ├── Supabase Storage (compatible S3, privé) : PDF et pièces jointes
   ├── Gemini, puis Groq en secours : réponses de l'Assistant IA
   └── Resend (API HTTP) : e-mails d'activation
```

| Couche | Où | Rôle |
|---|---|---|
| Routes | [`backend/routes/api.php`](../backend/routes/api.php) | Toutes les URL de l'API, avec leur middleware (`auth:sanctum`, `can:DROIT`, `throttle`). |
| Contrôleurs | `backend/app/Http/Controllers/` | Reçoivent la requête, valident, appellent la logique, renvoient du JSON. |
| Policies | `backend/app/Policies/` | « Cette personne a-t-elle le droit ? », vérifié **en base** à chaque requête. |
| Services | `backend/app/Services/` | Règles métier réutilisables (éligibilité, génération des groupes, IA…). |
| Front | `frontend/src/views/`, `frontend/src/components/` | Écrans ; `frontend/src/api/` = appels HTTP ; `frontend/src/stores/` = état (Pinia). |

**Client HTTP unique** : [`frontend/src/api/http.js`](../frontend/src/api/http.js) ajoute le jeton à chaque requête (l. 32) et, sur une 401, déconnecte l'utilisateur (l. 41-46).

**Protection des pages** : [`frontend/src/router/index.js`](../frontend/src/router/index.js) : `meta.auth`, `meta.profil`, `meta.droit` (l. 11-13, 57-91). Ce n'est qu'un confort d'affichage : **la vraie sécurité est côté serveur** (middleware `can:` dans `api.php`, Policies).

---

## 1. Administration (1:00 – 2:30)

### 1.1 Années universitaires : une seule active

- **Code** : [`AnneeController::activer`](../backend/app/Http/Controllers/Admin/AnneeController.php#L137) (l. 137) ; index unique dans la migration [`create_annees_universitaires_table`](../backend/database/migrations/2026_11_06_214553_create_annees_universitaires_table.php#L30) (l. 30).
- **Fonctionnement** : dans une transaction, on verrouille l'année active (`lockForUpdate`), on désactive toutes les années, puis on active la nouvelle. En option, on archive les groupes de l'année précédente.
- **Garantie en base** : `CREATE UNIQUE INDEX annee_une_seule_active ON annees_universitaires (active) WHERE active`. C'est un index **partiel**, qui interdit physiquement deux années actives, même en cas de bug ou de requêtes simultanées.
- **Phrase** : « La règle "une seule année active" est garantie par la base elle-même, pas seulement par le code. »

### 1.2 Comptes autorisés : liste, recherche, ajout

- **Code** : [`CompteAutoriseController`](../backend/app/Http/Controllers/Admin/CompteAutoriseController.php) : `index` (l. 69, recherche et filtres), `store` (l. 149). Écran : [`ComptesView.vue`](../frontend/src/views/admin/ComptesView.vue) (`enregistrer`, l. 225).
- **Fonctionnement** : c'est la liste fournie par la scolarité, qui décide **qui a le droit de créer un compte**. Le matricule et l'e-mail sont uniques (`Rule::unique`, l. 156 et 165), avec des messages d'erreur en français.
- **Accès** : la route est protégée par `can:GERER_UTILISATEURS` (`api.php`) : un étudiant reçoit 403, même en appelant l'API à la main.

### 1.3 Import Excel (aperçu puis validation)

- **Code** : [`ImportCompteController`](../backend/app/Http/Controllers/Admin/ImportCompteController.php) (`apercu` l. 49, `valider` l. 61) ; logique dans [`ImportComptesService`](../backend/app/Services/ImportComptesService.php) (`analyser` l. 196, `valider` l. 67). Écran : `ComptesView.vue` (`analyserLeFichier` l. 365, `lancerImport` l. 381).
- **Fonctionnement en deux temps** :
  1. **Aperçu** (rien n'est écrit) : le fichier est lu avec `maatwebsite/excel` (`Excel::toArray`, l. 119). Chaque ligne est vérifiée : champs obligatoires, format de l'e-mail (`filter_var`), profil connu, classe existante **dans l'année active**, doublons dans le fichier **et** dans la base. On renvoie les lignes valides et, pour chaque erreur, son numéro de ligne et le motif.
  2. **Validation** : seules les lignes valides sont insérées, dans **une transaction**. Les lignes devenues doublons entre-temps sont ignorées. L'import est historisé (table `imports_comptes`).
- **Fichier de démo** : `docs/demo/fichiers/import-comptes-demo.xlsx` (3 lignes valides, 1 doublon, 2 erreurs).
- **Phrase** : « L'administration voit les erreurs ligne par ligne **avant** d'importer ; un fichier à moitié faux n'écrit rien de faux. »

### 1.4 Groupes organisationnels : génération automatique

- **Code** : [`GenerationGroupesService`](../backend/app/Services/GenerationGroupesService.php) : `plan` (l. 176), `generer` (l. 83). Contrôleur : [`GroupeController::generer`](../backend/app/Http/Controllers/Admin/GroupeController.php#L154) (l. 154). Écran : [`GroupesView.vue`](../frontend/src/views/admin/GroupesView.vue) (`confirmerGeneration` l. 148).
- **Fonctionnement** :
  - **Plan** : un groupe par **classe** de l'année active (propriétaire : l'administration) et un groupe par **matière** (propriétaire : l'enseignant responsable). Le groupe de matière réunit les étudiants actifs de toutes les classes qui suivent cette matière.
  - **Génération** : `Espace::firstOrCreate(type, année, classe, matière)` puis `Adhesion::firstOrCreate(...)` pour chaque membre, le tout dans une transaction.
- **Idempotence** : relancer la génération ne crée **aucun doublon**, grâce à l'index unique `espace_orga_unique` (migration `create_espaces_table`, l. 45) et à `firstOrCreate`. L'écran affiche d'abord un aperçu (`apercu`, l. 41) sans rien écrire.
- **Phrase** : « Les espaces de classe et de matière se déduisent des référentiels ; on peut relancer la génération sans risque. »

---

## 2. Inscription avec e-mail (2:30 – 4:00)

### 2.1 Contrôle d'éligibilité (refus de `ETU2026-999`)

- **Code** : [`RegisterController::store`](../backend/app/Http/Controllers/RegisterController.php#L39) (l. 39) → [`EligibiliteService::verifier`](../backend/app/Services/EligibiliteService.php#L32) (l. 32). Écran : [`InscriptionView.vue`](../frontend/src/views/auth/InscriptionView.vue) (`envoyer` l. 70).
- **Fonctionnement**, trois contrôles dans l'ordre :
  1. Une année est-elle active ? Sinon `PLATEFORME_FERMEE`.
  2. Le **couple** matricule + e-mail figure-t-il dans `comptes_autorises` ? Sinon `NON_AUTORISE`. L'e-mail est comparé sans tenir compte des majuscules (`lower(email) = ?`).
  3. Un compte existe-t-il déjà pour ce matricule ? Sinon `DEJA_INSCRIT`.
- **Anti-abus** : `throttle:10,1` sur la route (10 essais par minute et par IP), pour qu'on ne puisse pas tester en masse qui figure dans la liste.

### 2.2 Création du compte et e-mail d'activation (`ETU2026-100`)

- **Code** : `RegisterController::store` (l. 50-100). Mail : `app/Mail/ActivationCompte.php`. Lien : [`LienActivation`](../backend/app/Services/LienActivation.php).
- **Fonctionnement** :
  - Dans une transaction, on crée le `User` **en attente**, **sans mot de passe**. Nom, profil et classe sont repris de la liste autorisée, qui reste la référence.
  - L'e-mail contient un **lien signé** valable 48 h (`URL::temporarySignedRoute`). Laravel signe l'URL avec la clé de l'application (HMAC), donc un lien modifié est refusé.
  - Si l'envoi échoue, le compte n'est **pas** perdu : la réponse dit `email_envoye: false` et l'écran propose « Renvoyer l'e-mail d'activation » (`renvoyerLien`, l. 108 ; `ActivationController::renvoyer`, l. 79).
- **Envoi en production** : API HTTP de **Resend** (`MAIL_MAILER=resend`), parce que l'offre gratuite de Render bloque le SMTP.

### 2.3 Activation : choix du mot de passe, connecté directement

- **Code** : [`VerifierLienActivation`](../backend/app/Http/Middleware/VerifierLienActivation.php#L25) (l. 25) → [`ActivationController::store`](../backend/app/Http/Controllers/ActivationController.php#L31) (l. 31) → [`RattachementService::rattacher`](../backend/app/Services/RattachementService.php#L33) (l. 33). Écran : [`ActivationView.vue`](../frontend/src/views/auth/ActivationView.vue) (`activer` l. 79).
- **Fonctionnement** :
  1. Le middleware recalcule la signature attendue et la compare avec `hash_equals` (comparaison à temps constant), puis vérifie l'expiration.
  2. Le contrôleur refuse un compte déjà actif (403) : le lien ne sert qu'une fois.
  3. Il hache le mot de passe (`Hash::make`, bcrypt), passe le compte en `ACTIF` et donne les droits par défaut du profil.
  4. Il **rattache automatiquement** l'étudiant au groupe de sa classe et aux groupes de ses matières (`firstOrCreate`, sans doublon).
  5. Il renvoie un jeton Sanctum : la personne arrive connectée, déjà membre de « L2 Informatique A » et de ses matières.

### 2.4 Connexion (comptes de démo)

- **Code** : [`LoginController::store`](../backend/app/Http/Controllers/Auth/LoginController.php#L31) (l. 31).
- **Fonctionnement** : compte inconnu et mot de passe faux donnent **le même message**, pour ne rien révéler. Après **5 échecs**, le compte est bloqué 15 minutes (`bloque_jusqua`, réponse 423). Un compte désactivé ou suspendu reçoit 403. En cas de succès, on crée un jeton Sanctum (`createToken`), que le front stocke et envoie dans l'en-tête `Authorization`.

---

## 3. Étudiant (4:00 – 6:00)

### 3.1 Accueil : mes espaces, non-lus, cloche

- **Code** : [`MesEspacesController::index`](../backend/app/Http/Controllers/MesEspacesController.php#L22) (l. 22). Écran : [`HomeView.vue`](../frontend/src/views/HomeView.vue). Notifications : [`NotificationController`](../backend/app/Http/Controllers/NotificationController.php), store [`notifications.js`](../frontend/src/stores/notifications.js).
- **Fonctionnement** : une seule requête SQL compte, pour chaque espace, les messages **non expirés** postérieurs au dernier message lu (jointure sur `adhesions`). Les espaces sont regroupés par type. La cloche interroge l'API toutes les 30 s ([`AppLayout.vue`](../frontend/src/layouts/AppLayout.vue#L108), l. 108, avec le composable [`usePolling`](../frontend/src/composables/usePolling.js), en pause quand l'onglet est caché).

### 3.2 Accepter l'invitation « Révisions BD – soutien »

- **Code** : [`InvitationController::accepter`](../backend/app/Http/Controllers/InvitationController.php#L115) (l. 115).
- **Fonctionnement** : on vérifie que l'invitation est **destinée à la personne connectée**, qu'elle est encore en attente et non expirée, et que l'espace n'est pas archivé. Ensuite, dans une transaction, l'invitation passe à « acceptée » et l'adhésion est créée. Les invitations expirent après 14 jours (`config/uniconnect.php`) ; une tâche planifiée les clôt chaque nuit (`routes/console.php`, `invitations:expirer` à 2 h).

### 3.3 Discussion : lire et envoyer (rafraîchissement 5 s)

- **Code** : [`MessageController`](../backend/app/Http/Controllers/MessageController.php) : `index` (l. 35), `store` (l. 79). Front : [`DiscussionTab.vue`](../frontend/src/components/espaces/DiscussionTab.vue) (`verifierNouveaux` l. 67, `envoyer` l. 90), [`Composer.vue`](../frontend/src/components/espaces/Composer.vue) (`soumettre` l. 136).
- **Fonctionnement** :
  - Avant de lire ou d'écrire, `authorize('view', $espace)` exige d'**être membre** ([`EspacePolicy::view`](../backend/app/Policies/EspacePolicy.php#L22), l. 22). Un espace archivé refuse les envois. En mode annonce, seuls les gestionnaires publient.
  - Le front interroge l'API toutes les 5 s (`usePolling`) pour récupérer les nouveaux messages. Il n'y a pas de WebSocket : l'offre gratuite de Render n'en permet pas.

### 3.4 Message avec PDF et « Messages visibles 30 jours »

- **Code** : `MessageController::store` (l. 79-125) et `calculerExpiration` (l. 169).
- **Fonctionnement** :
  - L'expiration `expire_le` est **figée à l'envoi** : maintenant + la durée de l'espace (ou celle choisie). Changer la règle plus tard n'est pas rétroactif.
  - La pièce jointe est stockée dans `espaces/{id}/…` sur Supabase Storage (`Storage::putFileAs`) et **expire en même temps** que le message (l. 118-119).
  - Les lectures filtrent `expire_le IS NULL OR expire_le > now()` (l. 50-52) : un message expiré disparaît partout, y compris dans le compteur de non-lus.

### 3.5 Télécharger un fichier : « même avec le lien direct »

- **Code** : [`FichierController::telecharger`](../backend/app/Http/Controllers/FichierController.php#L18) (l. 18) → [`FichierPolicy::view`](../backend/app/Policies/FichierPolicy.php#L20) (l. 20). Ressources de cours : [`RessourceController::telecharger`](../backend/app/Http/Controllers/RessourceController.php#L168) (l. 168).
- **Fonctionnement** : le stockage est **privé**, il n'y a aucune URL publique. Chaque téléchargement passe par l'API, qui vérifie **en base, à chaque requête** :
  1. que la personne est membre de l'espace (pas depuis le jeton : si elle est retirée, l'effet est immédiat) ;
  2. que le fichier n'est pas expiré ;
  3. que le message n'est ni masqué ni expiré.

  Si tout est bon, le fichier est envoyé en flux (`StreamedResponse`).
- **Extrait à montrer** (`FichierPolicy.php`, l. 28-36) :

  ```php
  // Adhésion contrôlée en base, jamais depuis le jeton : le retrait prend
  // effet dès la requête suivante.
  if (! $espace->adhesions()->where('user_id', $user->id)->exists()) {
      return false;
  }
  if ($fichier->expire_le !== null && $fichier->expire_le->isPast()) {
      return false;
  }
  ```

### 3.6 Créer un groupe privé et inviter

- **Code** : [`EspaceController::store`](../backend/app/Http/Controllers/EspaceController.php#L38) (l. 38), [`InvitationController::store`](../backend/app/Http/Controllers/InvitationController.php#L34) (l. 34). Écrans : [`CreerEspaceView.vue`](../frontend/src/views/espaces/CreerEspaceView.vue), [`MembresTab.vue`](../frontend/src/components/espaces/MembresTab.vue) (`envoyerInvitations` l. 247).
- **Fonctionnement** : il faut le **droit** `CREER_GROUPE_PRIVE`, donné à l'activation. Le créateur devient propriétaire, dans une transaction. On ne peut pas inviter dans un groupe organisationnel : ses membres viennent des référentiels (`InvitationController`, l. 197). Chaque invité reçoit une notification.

---

## 4. Enseignante (6:00 – 7:30)

### 4.1 Publier une annonce → notification chez Amine

- **Code** : [`AnnonceController::store`](../backend/app/Http/Controllers/AnnonceController.php#L42) (l. 42) ; notification `app/Notifications/NouvelleAnnonce.php`. Écran : [`AnnoncesTab.vue`](../frontend/src/components/espaces/AnnoncesTab.vue) (`publier` l. 105).
- **Fonctionnement** : `authorize('update', $espace)` réserve la publication aux gestionnaires (propriétaire, co-gestionnaire). L'annonce est enregistrée, puis `Notification::send($membres, new NouvelleAnnonce($annonce))` (l. 71) crée une notification en base pour chaque membre. La cloche d'Amine la récupère au prochain rafraîchissement.

### 4.2 Ressources : chapitres, PDF, badge « Indexé pour l'IA »

- **Code** :
  - [`RessourceController`](../backend/app/Http/Controllers/RessourceController.php) (`creer` l. 195) ;
  - [`RessourceObserver`](../backend/app/Observers/RessourceObserver.php#L33) (l. 33) → job [`IndexerRessource`](../backend/app/Jobs/IndexerRessource.php) → [`IndexationService`](../backend/app/Services/IndexationService.php) (`indexer` l. 28, `decouper` l. 116) ;
  - écran : [`RessourcesTab.vue`](../frontend/src/components/espaces/RessourcesTab.vue).
- **Fonctionnement** :
  1. Le PDF est stocké sur Supabase Storage.
  2. Un **observer** déclenche un **job en file d'attente** (`afterCommit` : il ne démarre qu'une fois la ressource enregistrée). L'enseignant n'attend donc pas.
  3. Le job extrait le texte page par page (`smalot/pdfparser`) et le découpe en **fragments d'environ 1 500 caractères**, avec un **chevauchement de 200 caractères** pour ne pas couper une idée en deux. Chaque fragment garde son numéro de page.
  4. Les fragments sont enregistrés dans `fragments_document`, puis `indexee_ia = true`, ce qui affiche le badge.
- **Recherche** : PostgreSQL calcule pour chaque fragment une colonne `tsvector` en français, indexée en **GIN** (migration `create_fragments_document_table`, l. 30-31). C'est elle qui sert à l'Assistant (étape 5).

### 4.3 Paramètres : réglage de l'Assistant IA

- **Code** : [`AssistantIAController::configurer`](../backend/app/Http/Controllers/AssistantIAController.php#L120) (l. 120) ; Policy `configurerAssistant` ([`EspacePolicy`](../backend/app/Policies/EspacePolicy.php#L45), l. 45). Écran : [`ReglageAssistantIA.vue`](../frontend/src/components/espaces/ReglageAssistantIA.vue).
- **Fonctionnement** : seul un **enseignant** propriétaire ou co-gestionnaire peut activer l'assistant et écrire une **consigne**, par exemple « réponds en donnant un exemple ». Cette consigne est ajoutée au prompt système (`AssistantService::promptSysteme`, l. 142).

---

## 5. Assistant IA (7:30 – 10:30)

### 5.1 Poser une question : le parcours complet

- **Code** :
  - [`AssistantIAController::poser`](../backend/app/Http/Controllers/AssistantIAController.php#L35) (l. 35) → [`AssistantService::poser`](../backend/app/Services/AssistantService.php#L29) (l. 29) ;
  - front : [`AssistantIATab.vue`](../frontend/src/components/espaces/AssistantIATab.vue) (`poser` l. 122), store [`ia.js`](../frontend/src/stores/ia.js).
- **Fonctionnement** : c'est du **RAG** (*retrieval-augmented generation*), c'est-à-dire « on cherche d'abord dans le cours, puis on fait rédiger l'IA ».
  1. **Contrôles** : être membre, assistant actif dans cet espace, **quota de 20 questions par jour** (l. 41-49, sinon 429).
  2. **Recherche** ([`chercherPostgres`](../backend/app/Services/AssistantService.php#L89), l. 89) : la question est transformée en requête plein texte française (`websearch_to_tsquery('french', …)`, mots combinés en OU). On garde les **6 fragments** de **cet espace seulement** les mieux classés par `ts_rank`.
  3. **Aucun extrait pertinent** → on **n'appelle pas l'IA** et on répond « le cours n'en parle pas ». Cela économise le quota gratuit et évite d'inventer.
  4. Sinon, on envoie à l'IA : le **prompt système** (« réponds uniquement à partir des extraits numérotés, cite [n], sinon réponds `JE_NE_SAIS_PAS` », l. 23-25), puis les extraits numérotés `[1] (fichier, p. 3) …` et la question.
  5. On récupère les numéros cités dans la réponse (`sourcesCitees`, l. 164, expression régulière). On ne garde que ceux qui existent vraiment, ce qui donne `[1] Chapitre 3 – Les curseurs PL/SQL · p. 3`.
  6. L'échange et ses sources sont enregistrés (tables `echanges_ia`, `sources_citees`), et la réponse indique le **quota restant**.

### 5.2 Question hors sujet (« Coupe du monde 2022 »)

- Soit la recherche ne trouve aucun fragment, et l'IA n'est même pas appelée (étape 3 ci-dessus). Soit l'IA répond `JE_NE_SAIS_PAS`, et le service renvoie le statut `SANS_SOURCE` (`AssistantService::poser`, l. 42). Dans les deux cas, l'écran dit que le cours n'en parle pas.

### 5.3 Clic sur une source → la ressource en évidence

- **Code** : [`SourcesAssistant.vue`](../frontend/src/components/espaces/SourcesAssistant.vue) (l. 67-70) et `SourcesCitees.vue` ; [`RessourcesTab.vue`](../frontend/src/components/espaces/RessourcesTab.vue) (l. 157, 488).
- **Fonctionnement** : le lien ouvre l'onglet Ressources avec `?ressource=<id>`. L'onglet fait défiler jusqu'au document (`scrollIntoView`) et le met en évidence (classe `ressources__ligne--evidence`).

### 5.4 Gemini, puis Groq en secours

- **Code** : [`ClientIAAvecSecours::repondre`](../backend/app/IA/ClientIAAvecSecours.php#L20) (l. 20), [`GeminiClient`](../backend/app/IA/GeminiClient.php), [`GroqClient`](../backend/app/IA/GroqClient.php) ; branchement dans [`AppServiceProvider`](../backend/app/Providers/AppServiceProvider.php#L23) (l. 23).
- **Fonctionnement** : le service dépend d'une **interface** `ClientIA`. L'implémentation essaie Gemini ; en cas d'erreur (quota, panne, délai dépassé), elle **bascule sur Groq**. Si les deux échouent, on renvoie une 503 « momentanément indisponible ». Dans les tests, l'interface est remplacée par un faux client, donc aucun appel réel et aucun quota consommé.
- **Extrait à montrer** :

  ```php
  try {
      return $this->principal->repondre($systeme, $message);   // Gemini
  } catch (ErreurFournisseurIA $e) {
      Log::info('Assistant IA : bascule vers Groq', [...]);
  }
  try {
      return $this->secours->repondre($systeme, $message);     // Groq
  } catch (ErreurFournisseurIA $e) {
      throw new IAIndisponibleException("L'assistant est momentanément indisponible.", 0, $e);
  }
  ```

### 5.5 Vote 👍 / 👎

- **Code** : [`AssistantIAController::avis`](../backend/app/Http/Controllers/AssistantIAController.php#L148) (l. 148) ; front `donnerAvis` (`AssistantIATab.vue`, l. 82).
- **Fonctionnement** : seul l'**auteur** de la question peut noter la réponse (l. 158). L'avis est enregistré sur l'échange, ce qui permet de suivre la qualité des réponses.

---

## 6. Mobile et qualité (10:30 – 12:00)

- **Responsive** :
  - [`AppLayout.vue`](../frontend/src/layouts/AppLayout.vue) : menu latéral sur ordinateur, barre de navigation en bas sur téléphone ;
  - `useDisplay()` de Vuetify : dialogues en plein écran sous 600 px (`:fullscreen="xs"`, SCRUM-172).
- **Accessibilité** :
  - lien « Aller au contenu » (`AppLayout.vue`) ;
  - contrastes renforcés (`plugins/vuetify.js`, `assets/main.css`) ;
  - score Lighthouse accessibilité : 100.
- **Tests** :
  - backend : `backend/tests/Feature/` (PHPUnit, PostgreSQL en CI) ;
  - frontend : fichiers `__tests__/*.spec.js` (Vitest) ;
  - la CI GitHub Actions lance backend, frontend et build Docker sur chaque PR, et une PR ne se fusionne qu'avec la CI verte et la relecture d'un autre membre.
- **Déploiement** :
  - front sur Vercel ;
  - API sur Render (Docker) : [`backend/docker/start.sh`](../backend/docker/start.sh) applique les migrations au démarrage ;
  - les clés (Gemini, Groq, Resend, base, stockage) sont dans les variables d'environnement, **jamais dans Git**.

---

## Questions « montrez-moi » fréquentes

| Question du jury | Fichier à ouvrir |
|---|---|
| Où vérifiez-vous les droits ? | `backend/app/Policies/EspacePolicy.php`, `FichierPolicy.php` + middleware `can:` dans `routes/api.php` |
| Comment le mot de passe est-il stocké ? | `ActivationController.php` l. 47 : `Hash::make` (bcrypt), jamais en clair |
| Comment l'IA trouve-t-elle les passages ? | `AssistantService::chercherPostgres` (l. 89) + colonne `tsvector` / index GIN |
| Comment empêchez-vous l'IA d'inventer ? | Prompt système `AssistantService.php` l. 23-25 + pas d'appel sans extrait (l. 34-37) |
| Comment le PDF devient-il « lisible » par l'IA ? | `IndexationService::indexer` / `decouper` (fragments de 1 500 car., chevauchement 200) |
| Que se passe-t-il si Gemini tombe ? | `app/IA/ClientIAAvecSecours.php` |
| Comment expirent les messages ? | `MessageController::calculerExpiration` (l. 169) + filtre l. 50-52 |
| Comment évitez-vous les doublons de groupes ? | `GenerationGroupesService::generer` : `firstOrCreate` + index unique |
| Comment le lien d'activation est-il sécurisé ? | `URL::temporarySignedRoute` (48 h) + `VerifierLienActivation` (`hash_equals`) |
| Pourquoi pas de temps réel ? | `frontend/src/composables/usePolling.js` : rafraîchissement 5 s, pause onglet caché |
