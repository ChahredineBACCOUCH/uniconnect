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
| POST | `/auth/activate` | public | `{ id, hash, expires, signature, password, password_confirmation }` (paramètres du lien signé) → **200** `{ token, user }` | 403 (lien invalide), 410 (lien expiré), 422 (mot de passe trop faible) | SCRUM-66 |
| POST | `/auth/activation/renvoyer` | public | `{ email }` → **202** `{ message }` (toujours le même message) | 429 | SCRUM-66 |
| POST | `/login` | public | `{ email, password }` → **200** `{ token, user }` | 422 (identifiants incorrects), 423 (bloqué 15 min), 403 (suspendu ou désactivé) | SCRUM-69 |
| POST | `/logout` | connecté | — → **204** (le jeton est supprimé) | — | SCRUM-69 |
| GET | `/user` ✅ | connecté | → `Utilisateur` | 401 | SCRUM-69 |

> Les noms `/login`, `/logout` et `/user` sont déjà utilisés par le frontend (`AUTH_ROUTES` dans `frontend/src/stores/auth.js`).
> Règles du mot de passe : 8 caractères minimum, au moins une majuscule, une minuscule et un chiffre.

## 3. Espaces

Objet `EspaceResume` : `{ id, type, nom, image, role, non_lus, dernier_message: { extrait, auteur, date } | null, assistant_actif }`.
`type` peut valoir `ORGANISATIONNEL` ou `PRIVE` (`CHANNEL` en v1.1), et `role` : `PROPRIETAIRE`, `CO_GESTIONNAIRE` ou `MEMBRE`.

| Méthode | Route | Accès | Corps → Réponse | Ticket |
|---|---|---|---|---|
| GET | `/me/espaces` | connecté | → `{ data: EspaceResume[] }`, groupés côté front (classe, matières, groupes privés) | SCRUM-85 |
| GET | `/espaces/{id}` | membre | → `{ id, type, nom, description, image, role, nb_membres, assistant: { actif, instructions } }` | SCRUM-94 |
| POST | `/espaces` | droit `CREER_GROUPE_PRIVE` | `{ nom, description?, invites?: [user_id] }` → **201** `EspaceResume` (le créateur devient `PROPRIETAIRE`) | SCRUM-87 |
| PATCH | `/espaces/{id}` | propriétaire / co-gestionnaire | `{ nom?, description? }` → espace | SCRUM-87 |
| PATCH | `/espaces/{id}/assistant` | enseignant propriétaire / co-gestionnaire | `{ actif, instructions? }` → `{ actif, instructions, nb_documents_indexes }` | SCRUM-145 |
| POST | `/espaces/{id}/lu` | membre | `{ dernier_message_id }` → **204** (remet `non_lus` à 0) | SCRUM-95 |

## 4. Invitations et membres

| Méthode | Route | Accès | Corps → Réponse | Ticket |
|---|---|---|---|---|
| GET | `/utilisateurs?q=ben` | connecté | → `{ data: [{ id, nom, prenom, profil, classe }] }`, 10 maximum (pour inviter) | SCRUM-88 |
| POST | `/espaces/{id}/invitations` | propriétaire / co-gestionnaire | `{ user_ids: [..] }` → **201** `{ creees: n, deja_membres: [..], deja_invites: [..] }` | SCRUM-88 |
| GET | `/me/invitations` | connecté | → `{ data: [{ id, espace: { id, nom }, invite_par, expire_le }] }` | SCRUM-88 |
| POST | `/invitations/{id}/accepter` | invité | → **200** `EspaceResume` | SCRUM-88 |
| POST | `/invitations/{id}/refuser` | invité | → **204** | SCRUM-88 |
| DELETE | `/invitations/{id}` | auteur de l'invitation | → **204** (annulée) | SCRUM-88 |
| GET | `/espaces/{id}/membres` | membre | → `{ data: [{ user: {id, nom, prenom, profil}, role, depuis }] }` | SCRUM-90 |
| PATCH | `/espaces/{id}/membres/{userId}` | propriétaire | `{ role: "CO_GESTIONNAIRE" \| "MEMBRE" }` → membre | SCRUM-90 |
| DELETE | `/espaces/{id}/membres/{userId}` | propriétaire / co-gestionnaire | → **204** (accès coupé immédiatement) | SCRUM-90 |
| POST | `/espaces/{id}/quitter` | membre d'un groupe privé | → **204** (403 pour un groupe organisationnel ; 409 pour le dernier propriétaire) | SCRUM-90 |

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
| GET | `/admin/annees` | `GERER_PARAMETRAGE` | → liste `{ id, libelle, date_debut, date_fin, active }` | SCRUM-72 |
| POST | `/admin/annees` | `GERER_PARAMETRAGE` | `{ libelle: "2027/2028", date_debut, date_fin }` → **201** | SCRUM-72 |
| PATCH | `/admin/annees/{id}` | `GERER_PARAMETRAGE` | `{ libelle?, date_debut?, date_fin? }` → année | SCRUM-72 |
| POST | `/admin/annees/{id}/activer` | `GERER_PARAMETRAGE` | → année active (l'ancienne est désactivée dans la même transaction) | SCRUM-72 |
| GET/POST | `/admin/referentiels/{type}` | `GERER_PARAMETRAGE` | `type` ∈ `departements`, `filieres`, `niveaux`, `classes`, `matieres` | SCRUM-79 |
| PATCH/DELETE | `/admin/referentiels/{type}/{id}` | `GERER_PARAMETRAGE` | suppression refusée (**409**) si l'élément est utilisé | SCRUM-79 |
| GET | `/admin/comptes-autorises?profil=&classe_id=&etat=&q=` | `GERER_UTILISATEURS` | → liste paginée `{ id, matricule, nom, prenom, email, profil, classe, etat }` | SCRUM-75 |
| POST/PATCH/DELETE | `/admin/comptes-autorises[/{id}]` | `GERER_UTILISATEURS` | création, modification, suppression unitaire | SCRUM-75 |
| POST | `/admin/comptes-autorises/import?apercu=1` | `GERER_UTILISATEURS` | multipart `fichier` (CSV) → `{ valides: n, erreurs: [{ ligne, motif }] }`, sans rien enregistrer | SCRUM-76 |
| POST | `/admin/comptes-autorises/import` | `GERER_UTILISATEURS` | même fichier → **201** `{ importes: n, ignores: n }` | SCRUM-76 |
| POST | `/admin/groupes/generer?apercu=1` | `CREER_GROUPE_ORGA` | → `{ a_creer: [...], deja_existants: n }` | SCRUM-82 |
| POST | `/admin/groupes/generer` | `CREER_GROUPE_ORGA` | → `{ crees: n, membres_ajoutes: n }`, peut être relancé sans doublons ; **422** s'il n'y a pas d'année active | SCRUM-82 |

Colonnes du fichier CSV d'import : `matricule;nom;prenom;email;profil;classe_code`. Séparateur `;`, encodage UTF-8, `classe_code` vide pour les non-étudiants.

---

## Routes reportées en v1.1 (ne pas coder avant le 19/11)

Mot de passe oublié (`/auth/mot-de-passe/*`), channels et demandes d'adhésion, épingler (`PATCH /messages/{id}/epingle`), onglet Fichiers (`GET /espaces/{id}/fichiers`), signalements et modération, droits des utilisateurs (`/admin/utilisateurs`), tableau de bord, historique, recherche (`/recherche`), résumé IA (`/ia/resume`), règles de visibilité (`/admin/regles`), profil (`/me/profil`).
