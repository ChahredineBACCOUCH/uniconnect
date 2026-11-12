# Contrat de l'API (version livrée le 19/11)

Ce document est le **contrat** entre le backend (Laravel) et le frontend (Vue) :
- la personne qui code une route la crée **exactement** comme décrit ici ;
- la personne qui code l'écran l'appelle comme décrit ici.

Si une route doit changer, modifiez ce fichier **dans la même PR** et prévenez la personne qui code l'autre côté.

✅ = déjà en place dans `main` · les autres routes sont à créer dans le ticket indiqué.

## Conventions

| Sujet | Règle |
|---|---|
| Adresse | Toutes les routes commencent par `/api`. En local : `http://localhost:8000/api`, définie dans `VITE_API_URL`. |
| Format | JSON en entrée et en sortie (en-tête `Accept: application/json`). Envois de fichiers en `multipart/form-data`. |
| Authentification | Sauf mention « public », envoyer `Authorization: Bearer <jeton>`. `src/api/http.js` le fait automatiquement. |
| Noms | Champs en français et en `snake_case` : `date_debut`, `non_lus`. |
| Dates | ISO 8601 avec fuseau, par exemple `2026-11-12T14:05:00+01:00`. |
| Listes paginées | `{ "data": [...], "meta": { "page": 1, "par_page": 20, "total": 134 } }`, paramètres `?page=` et `?par_page=` (100 maximum). |
| Langue | Tous les messages d'erreur sont en **français**, prêts à être affichés. |

### Codes de réponse

| Code | Signification | Ce que fait le frontend |
|---|---|---|
| 200 / 201 / 204 | Succès (201 : créé ; 204 : rien à renvoyer) | — |
| 401 | Pas connecté ou jeton expiré | `http.js` vide la session et renvoie vers `/connexion` |
| 403 | Connecté mais pas autorisé (non-membre, droit manquant) | Afficher la page 403 ou un message |
| 404 | N'existe pas, ou fonctionnalité désactivée (assistant inactif) | Afficher la page 404 |
| 409 | Conflit (déjà fait : compte déjà créé, invitation déjà envoyée…) | Afficher `message` |
| 422 | Formulaire invalide → `{ "message": "...", "errors": { "email": ["..."] } }` | `http.js` lève une `ValidationError` ; afficher `errors` sous chaque champ |
| 423 | Compte bloqué (trop d'échecs de connexion) | Afficher `message` (« Réessayez dans 15 minutes ») |
| 429 | Trop de requêtes, ou quota IA atteint | Afficher `message` |
| 503 | Service indisponible (les deux IA ne répondent pas) | Afficher « L'assistant est momentanément indisponible » + bouton « Réessayer » |

### Objet `Utilisateur` (renvoyé par `/login` et `/user`)

```json
{
  "id": 12,
  "matricule": "LI2-2026-0457",
  "nom": "Ben Salah",
  "prenom": "Yassine",
  "email": "yassine.bensalah@univ.tn",
  "profil": "ETUDIANT",
  "classe": { "id": 3, "libelle": "L2 Informatique A" },
  "photo": null,
  "droits": ["CREER_GROUPE_PRIVE"]
}
```

`profil` peut valoir `ETUDIANT`, `ENSEIGNANT` ou `ADMINISTRATION`. Les valeurs possibles de `droits` sont : `GERER_PARAMETRAGE`, `GERER_UTILISATEURS`, `CREER_GROUPE_ORGA`, `CREER_GROUPE_PRIVE`, `CREER_CHANNEL`, `MODERER`, `CONSULTER_TABLEAU_BORD`. Le frontend les teste avec `auth.hasDroit('…')`.

---

## 1. Santé

| Méthode | Route | Accès | Réponse | Ticket |
|---|---|---|---|---|
| GET | `/health` ✅ | public | `{ "status": "ok", "version": "1.0.0", "db": "ok" }` | SCRUM-24 |

## 2. Authentification et comptes

| Méthode | Route | Accès | Corps → Réponse | Erreurs | Ticket |
|---|---|---|---|---|---|
| POST | `/auth/register` | public | `{ matricule, nom, prenom, email, accepte_charte }` → **201** `{ message }` + e-mail d'activation | 422 (pas sur la liste, e-mail ≠ liste, charte non cochée), 409 (compte déjà créé), 422 (aucune année active) | SCRUM-63 |
| POST | `/auth/activate/{id}` | public | `?expires=…&signature=…` (paramètres du lien signé, à renvoyer tels quels) + `{ password, password_confirmation }` → **200** `{ token, user }` (jeton Sanctum : la personne est connectée). Rattache automatiquement l'étudiant à son groupe de classe et aux groupes de ses matières, et l'enseignant propriétaire aux groupes de ses matières | 403 (lien invalide, expiré ou compte déjà activé), 422 (mot de passe trop faible) | SCRUM-66, 68 |
| POST | `/auth/activate/renvoyer` | public | `{ email }` → **202** `{ message }` (toujours le même message ; l'e-mail n'est renvoyé que si le compte est encore en attente d'activation) | 422 (e-mail invalide), 429 | SCRUM-66 |
| POST | `/auth/login` | public | `{ email, password }` → **200** `{ token, user }` | 422 (identifiants incorrects), 423 (bloqué 15 min après 5 échecs), 403 (suspendu ou désactivé) | SCRUM-69 |
| POST | `/auth/logout` | connecté | — → **204** (le jeton est supprimé) | — | SCRUM-69 |
| GET | `/me` | connecté | → `Utilisateur` | 401 | SCRUM-69 |

> Les noms `/auth/login`, `/auth/logout` et `/me` sont utilisés par le frontend (`AUTH_ROUTES` dans `frontend/src/stores/auth.js`).
> Règles du mot de passe : 8 caractères minimum, au moins une majuscule, une minuscule et un chiffre.
> Le lien d'activation (page Vue `/activation/{id}`) est signé pour 48 h : le frontend renvoie ses paramètres `expires` et `signature` **dans la chaîne de requête** de `POST /auth/activate/{id}`, le contrôle de la signature et de l'expiration étant fait par le middleware `VerifierLienActivation`.

## 3. Espaces

Objet `EspaceResume` : `{ id, type, nom, image, role, non_lus, dernier_message: { extrait, auteur, date } | null, assistant_actif }`.
`type` peut valoir `ORGANISATIONNEL` ou `PRIVE` (`CHANNEL` en v1.1), et `role` : `PROPRIETAIRE`, `CO_GESTIONNAIRE` ou `MEMBRE`.

| Méthode | Route | Accès | Corps → Réponse | Ticket |
|---|---|---|---|---|
| GET | `/me/espaces` | connecté | → `{ organisationnels: [], prives: [], channels: [] }`, chaque espace `{ id, nom, type, icone, role, etat, non_lus, visibilite_limitee }`, triés par nom. `non_lus` = messages visibles des autres après le dernier message lu (une seule requête SQL) | SCRUM-85 |
| GET | `/espaces/{id}` | membre | → `{ id, type, nom, description, image, etat, role, nb_membres, duree_visibilite_heures, duree_courte_autorisee, mode_annonce, assistant: { actif, instructions, nb_documents_indexes } }` | SCRUM-94, 95, 97 |
| POST | `/espaces` | droit `CREER_GROUPE_PRIVE` | `{ nom (3 à 80 car.), description?, duree_visibilite_heures?, duree_courte_autorisee?, mode_annonce? }` → **201** `{ data: espace }` (même format que `GET /espaces/{id}`, le créateur devient `PROPRIETAIRE`). `duree_visibilite_heures` parmi `24`, `168`, `720` ; absente = 168 (7 jours) ; `null` = illimitée. `mode_annonce: true` = seuls les gestionnaires publient. Sans le droit → **403**. Les invités s'ajoutent ensuite avec `POST /espaces/{id}/invitations` (écran E06 : `/espaces/nouveau`) | SCRUM-87, 89 |
| PATCH | `/espaces/{id}` | propriétaire / co-gestionnaire | `{ nom?, description? }` → `{ data: espace }` ; **403** si l'espace est archivé | SCRUM-87 |
| PATCH | `/espaces/{id}/assistant` | enseignant propriétaire / co-gestionnaire | `{ actif, instructions? }` → `{ actif, instructions, nb_documents_indexes }` | SCRUM-145 |
| POST | `/espaces/{id}/lu` | membre | `{ dernier_message_id }` → **204** (remet `non_lus` à 0) | SCRUM-95 |

## 4. Invitations et membres

| Méthode | Route | Accès | Corps → Réponse | Ticket |
|---|---|---|---|---|
| GET | `/utilisateurs?q=ben` | connecté | → `{ data: [{ id, nom, prenom, profil, classe }] }`, comptes **actifs** uniquement, 10 maximum, sans l'utilisateur connecté ; `q` d'au moins 2 caractères (sinon 422). Cherche dans le nom, le prénom et « prénom nom » | SCRUM-88 |
| POST | `/espaces/{id}/invitations` | propriétaire / co-gestionnaire | `{ user_ids: [..] }` (1 à 50) → **201** `{ creees: n, deja_membres: [..], deja_invites: [..] }`. Expire au bout de 14 jours. **403** espace archivé ; **422** groupe organisationnel | SCRUM-88 |
| GET | `/espaces/{id}/invitations` | propriétaire / co-gestionnaire | → `{ data: [{ id, etat, invite, invite_par, expire_le, cree_le }] }` (en attente), pour les annuler depuis E07 | SCRUM-88 |
| GET | `/me/invitations` | connecté | → `{ data: [{ id, etat, espace: { id, nom }, invite_par, expire_le, cree_le }] }` (en attente, non expirées) | SCRUM-88 |
| POST | `/invitations/{id}/accepter` | invité | → **200** `{ data: espace }` (rôle `MEMBRE`) ; **422** si expirée ou déjà traitée ; **403** si ce n'est pas l'invité | SCRUM-88 |
| POST | `/invitations/{id}/refuser` | invité | → **204** ; **422** si expirée ou déjà traitée | SCRUM-88 |
| DELETE | `/invitations/{id}` | auteur de l'invitation ou propriétaire de l'espace | → **204** (annulée) ; **422** si elle n'est plus en attente | SCRUM-88 |
| GET | `/espaces/{id}/membres?q=` | membre | → `{ data: [{ user: {id, nom, prenom, profil}, role, depuis }] }`, propriétaire puis co-gestionnaires puis membres | SCRUM-90 |
| PATCH | `/espaces/{id}/membres/{userId}` | propriétaire | `{ role: "CO_GESTIONNAIRE" \| "MEMBRE" }` → `{ data: membre }` ; **422** pour le propriétaire (passer par `/transferer`) | SCRUM-90 |
| DELETE | `/espaces/{id}/membres/{userId}` | propriétaire / co-gestionnaire | → **204** (accès coupé immédiatement). **422** pour le propriétaire ou soi-même (utiliser `/quitter`) ; **403** si un co-gestionnaire retire un autre co-gestionnaire | SCRUM-90 |
| POST | `/espaces/{id}/transferer` | propriétaire | `{ user_id }` (membre de l'espace) → `{ data: membre }` ; l'ancien propriétaire devient co-gestionnaire | SCRUM-90 |
| POST | `/espaces/{id}/quitter` | membre | → **204**. **403** pour un groupe organisationnel. Si le propriétaire part : la propriété passe au plus ancien co-gestionnaire, sinon à un compte de l'administration ; **409** s'il n'y en a aucun | SCRUM-90 |

## 5. Messages et pièces jointes

Objet `Message` : `{ id, auteur: { id, nom, prenom, profil, badge } | null, contenu, parent_id, expire_le, modifie, modifie_le, cree_le, pieces_jointes: [{ id, nom, mime, taille_octets }] }`.

| Méthode | Route | Accès | Corps → Réponse | Ticket |
|---|---|---|---|---|
| GET | `/espaces/{id}/messages?after_id=&limite=30` | membre | → `{ data: Message[], meta: { next_cursor, prev_cursor, per_page } }`, du plus ancien au plus récent ; pagination par curseur (`cursor`) ; `after_id` sert au rafraîchissement toutes les 5 s | SCRUM-94 |
| POST | `/espaces/{id}/messages` | membre | `{ contenu, parent_id?, duree_visibilite_heures? }` (+ `fichiers[]` en SCRUM-136) → **201** `Message`. `expire_le` est calculé à l'envoi ; 403 si l'espace est archivé ou en mode annonce pour un membre simple. | SCRUM-94, 136 |
| PATCH | `/messages/{id}` | auteur | `{ contenu }` → `Message` (remplit `modifie_le`) | SCRUM-94 |
| DELETE | `/messages/{id}` | auteur | → **204** | SCRUM-94 |
| GET | `/fichiers/{id}/telecharger` | membre de l'espace | → **302** vers un lien temporaire (5 min), ou envoi direct du fichier en local | SCRUM-137 |

Chaque lecture met à jour `adhesions.dernier_message_lu_id` (le curseur ne recule jamais). Les messages masqués et les messages expirés ne sont pas renvoyés.

Règles des fichiers : taille maximale fixée par `regles_plateforme.taille_max_fichier_mo` ; formats autorisés : PDF, images, Office, ZIP. Un fichier trop gros ou interdit renvoie **422**.

## 6. Annonces et ressources

| Méthode | Route | Accès | Corps → Réponse | Ticket |
|---|---|---|---|---|
| GET | `/espaces/{id}/annonces` | membre | → `{ data: [{ id, titre, contenu, auteur, cree_le }] }` (les plus récentes d'abord) | SCRUM-123 |
| POST | `/espaces/{id}/annonces` | enseignant de l'espace | `{ titre, contenu, notifier_email? }` → **201** annonce (+ notification aux membres) | SCRUM-123 |
| DELETE | `/annonces/{id}` | auteur | → **204** | SCRUM-123 |
| GET | `/espaces/{id}/chapitres` | membre | → `{ data: [{ id, titre, ordre, ressources: [{ id, titre, description, mime, url, indexee_ia, cree_le }] }] }` | SCRUM-126 |
| POST | `/espaces/{id}/chapitres` | enseignant de l'espace | `{ titre }` → **201** chapitre | SCRUM-126 |
| PATCH | `/chapitres/{id}` | enseignant | `{ titre?, ordre? }` → chapitre | SCRUM-126 |
| POST | `/chapitres/{id}/ressources` | enseignant | multipart : `titre`, `description?`, `fichier` **ou** `url` → **201** ressource (un PDF lance l'indexation IA) | SCRUM-126, 98 |
| DELETE | `/ressources/{id}` | enseignant | → **204** (supprime aussi les fragments IA) | SCRUM-126 |
| GET | `/ressources/{id}/telecharger` | membre | → **302** lien temporaire | SCRUM-126 |

## 7. Assistant IA

| Méthode | Route | Accès | Corps → Réponse | Ticket |
|---|---|---|---|---|
| POST | `/espaces/{id}/ia/questions` | membre, assistant actif | `{ question, conversation_id? }` → `{ conversation_id, echange_id, reponse, statut, sources: [{ n, fichier, page, ressource_id }], quota_restant }` | SCRUM-100, 102 |
| PATCH | `/ia/echanges/{id}/avis` | auteur de la question | `{ utile: true \| false }` → **204** | SCRUM-146 |

- `statut` vaut `OK`, ou `SANS_SOURCE` (rien de pertinent dans le cours ; l'IA n'est pas appelée).
- **404** : assistant désactivé dans cet espace · **429** : `{ message: "Vous avez atteint vos 20 questions du jour. Revenez demain." }` · **503** : Gemini et Groq ne répondent pas.
- Une question fait 3 à 1 000 caractères (sinon 422).
- `conversation_id` absent : une nouvelle conversation est créée. Une conversation d'une autre personne ou d'un autre espace donne **404**.
- Le quota (20 par jour, `config/uniconnect.php`) compte les questions de la personne dans tous les espaces depuis minuit ; une question en échec (503) n'est pas comptée.

## 8. Notifications

| Méthode | Route | Accès | Réponse | Ticket |
|---|---|---|---|---|
| GET | `/notifications?non_lues=1` | connecté | → `{ data: [{ id, type, titre, texte, lien, lue, cree_le }], meta: { non_lues } }` | SCRUM-121 |
| POST | `/notifications/{id}/lu` | destinataire | → **204** | SCRUM-121 |
| POST | `/notifications/tout-lu` | connecté | → **204** | SCRUM-121 |

`type` peut valoir : `INVITATION`, `ANNONCE`, `RESSOURCE`, `MEMBRE_AJOUTE`.

## 9. Administration

Toutes ces routes exigent `profil = ADMINISTRATION` **et** le droit indiqué (sinon **403**).

| Méthode | Route | Droit | Corps → Réponse | Ticket |
|---|---|---|---|---|
| GET | `/admin/annees` | `GERER_PARAMETRAGE` | → liste `{ id, libelle, date_debut, date_fin, active, nombre_groupes, nombre_groupes_archives }` (`nombre_groupes` : groupes organisationnels de l'année, pour l'écran A01) | SCRUM-72, 73 |
| POST | `/admin/annees` | `GERER_PARAMETRAGE` | `{ libelle: "2027/2028", date_debut, date_fin }` → **201** | SCRUM-72 |
| PATCH | `/admin/annees/{id}` | `GERER_PARAMETRAGE` | `{ libelle?, date_debut?, date_fin? }` → année | SCRUM-72 |
| POST | `/admin/annees/{id}/activer` | `GERER_PARAMETRAGE` | `{ archiver_precedente? }` → année active (l'ancienne est désactivée dans la même transaction ; `archiver_precedente` archive les espaces de l'ancienne année) | SCRUM-72 |
| GET/POST | `/admin/{referentiel}` | `GERER_PARAMETRAGE` | `referentiel` ∈ `departements`, `filieres`, `niveaux`, `classes`, `matieres` ; listes paginées `?actif=&q=&page=&par_page=` et création → **201** | SCRUM-79 |
| GET/PATCH/DELETE | `/admin/{referentiel}/{id}` | `GERER_PARAMETRAGE` | détail, modification, suppression ; **409** `{ message }` si l'élément est utilisé | SCRUM-79 |
| PATCH | `/admin/{referentiel}/{id}/desactiver` | `GERER_PARAMETRAGE` | → élément à `actif: false` (aucune suppression) | SCRUM-79 |
| GET | `/admin/matieres/enseignants` | `GERER_PARAMETRAGE` | → enseignants actifs `{ data: [{ id, nom, prenom, email }] }` pour les sélecteurs de l'écran A02 | SCRUM-80 |
| GET | `/admin/comptes-autorises?profil=&classe_id=&etat=&q=` | `GERER_UTILISATEURS` | → liste paginée `{ id, matricule, nom, prenom, email, profil, classe, etat }` | SCRUM-75 |
| POST/PATCH/DELETE | `/admin/comptes-autorises[/{id}]` | `GERER_UTILISATEURS` | création, modification, suppression unitaire ; **409** si l'autorisation est déjà rattachée à un compte créé | SCRUM-75 |
| POST | `/admin/comptes-autorises/{id}/desactiver` | `GERER_UTILISATEURS` | → autorisation à l'état `DESACTIVE` (aucune suppression) | SCRUM-75 |
| POST | `/admin/comptes-autorises/import/apercu` | `GERER_UTILISATEURS` | multipart `fichier` (Excel) → `{ total, valides, erreurs: [{ ligne, motif }], lignes }`, sans rien enregistrer | SCRUM-76 |
| POST | `/admin/comptes-autorises/import/valider` | `GERER_UTILISATEURS` | `{ lignes: [...] }` issues de l'aperçu → **201** `{ importes, ignores, erreurs, import_id }` | SCRUM-76 |
| GET | `/admin/comptes-autorises/import/modele` | `GERER_UTILISATEURS` | → modèle Excel `modele-import-comptes.xlsx` | SCRUM-76 |
| GET | `/admin/comptes-autorises/classes` | `GERER_UTILISATEURS` | → classes actives de l'année active `{ data: [{ id, code, libelle }] }` | SCRUM-77 |
| GET | `/admin/groupes?filiere_id=&niveau_id=&classe_id=&enseignant_id=` | `CREER_GROUPE_ORGA` | → `{ annee, data: [{ id, nom, type, etat, classe, matiere, classes, proprietaire, nombre_membres }], total }` pour l'écran A06. Un groupe de matière couvre toutes les classes de l'année qui suivent la matière ; sans année active : `200` `{ annee: null, data: [], total: 0 }` | SCRUM-83 |
| GET | `/admin/groupes/filtres` | `CREER_GROUPE_ORGA` | → `{ annee, filieres, niveaux, classes, enseignants }` (options des filtres de A06 ; listes vides sans année active) | SCRUM-83 |
| POST | `/admin/groupes/{id}/synchroniser` | `CREER_GROUPE_ORGA` | relance le rattachement d'un groupe → `{ groupe, membres_ajoutes }` (n'ajoute que les membres manquants) ; **409** si le groupe est archivé | SCRUM-83 |
| POST | `/admin/groupes/{id}/archiver` | `CREER_GROUPE_ORGA` | → `{ groupe }` à l'état `ARCHIVE` (idempotent) | SCRUM-83 |
| GET | `/admin/groupes/apercu` | `CREER_GROUPE_ORGA` | → `{ annee, total, a_creer: [...], deja_existants: n, membres_a_ajouter: n }`, sans rien enregistrer ; **422** s'il n'y a pas d'année active | SCRUM-82 |
| POST | `/admin/groupes/generer?apercu=1` | `CREER_GROUPE_ORGA` | même réponse que `GET /admin/groupes/apercu` (compatibilité) | SCRUM-82 |
| POST | `/admin/groupes/generer` | `CREER_GROUPE_ORGA` | un groupe par classe + un groupe par matière (toutes classes confondues) → `{ annee, crees: n, deja_existants: n, membres_ajoutes: n }`, relançable sans doublon ; **422** s'il n'y a pas d'année active | SCRUM-82 |

Référentiels : `code` unique (et `code` + `annee_id` pour une classe). `POST`/`PATCH /admin/matieres[/{id}]` acceptent aussi `classe_ids[]` et `enseignant_ids[]`, synchronisés sur les tables pivot. Supprimer un élément encore utilisé répond **409** `{ message: "Impossible de supprimer : élément utilisé. Vous pouvez le désactiver." }`, y compris une classe qui a des étudiants.

Format du fichier d'import : Excel (`.xlsx` ou `.xls`), première feuille, colonnes `matricule`, `nom`, `prenom`, `email`, `profil`, `classe_code`. `classe_code` est vide pour les non-étudiants et doit exister pour l'année universitaire active. L'aperçu n'enregistre rien ; la validation ignore les lignes invalides ou devenues des doublons et conserve l'historique dans `imports_comptes`.

---

## Routes reportées en v1.1 (ne pas coder avant le 19/11)

Mot de passe oublié (`/auth/mot-de-passe/*`), channels et demandes d'adhésion, épingler (`PATCH /messages/{id}/epingle`), onglet Fichiers (`GET /espaces/{id}/fichiers`), signalements et modération, droits des utilisateurs (`/admin/utilisateurs`), tableau de bord, historique, recherche (`/recherche`), résumé IA (`/ia/resume`), règles de visibilité (`/admin/regles`), profil (`/me/profil`).
