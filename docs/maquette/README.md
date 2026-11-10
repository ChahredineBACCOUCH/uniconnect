# Maquette UniConnect — guide pour l'IA de développement

Ce dossier décrit **exactement** l'interface à coder. Donne-le à ton assistant IA (ChatGPT, Gemini, Copilot, Cursor…) avec le ticket Jira sur lequel tu travailles.

| Fichier | Contenu | Comment l'utiliser |
|---|---|---|
| `README.md` (ce fichier) | Règles de design, thème Vuetify, liste de tous les écrans avec route, fichier Vue, API, états et ticket Jira | À coller **en entier** dans la conversation avec l'IA |
| `prototype-v1.html` | Prototype cliquable de la version 1.0 (tous les écrans, 3 profils) | L'ouvrir dans le navigateur pour **voir** le résultat attendu ; donner à l'IA la partie HTML/CSS de l'écran concerné |
| `ecrans/*.dc.html` | Code des écrans de la maquette Claude Design (HTML + CSS, données de démo) | Référence de mise en page **écran par écran**. Ces fichiers ne s'ouvrent pas seuls (il leur manque le moteur de la maquette) : donne leur contenu à l'IA comme modèle visuel |

Maquette en ligne (privée, à partager depuis le menu Share) : https://claude.ai/artifact/AAKmr1GqtVatRDSkhGKb4k

---

## 1. Prompt à donner à l'IA

Copie ce texte, remplace les `…`, puis colle en dessous ce README et le fichier de l'écran concerné.

```
Tu es développeur front-end Vue 3 sur le projet UniConnect (projet étudiant).
Stack : Vue 3 (Composition API, <script setup>), Vuetify 3, Pinia, Vue Router, Axios (src/api/http.js).
Backend : API Laravel + Sanctum (jeton Bearer), réponses JSON en français.

Je travaille sur le ticket Jira SCRUM-… : « … ».
Respecte strictement la maquette décrite dans le README ci-dessous :
- thème Vuetify « uniconnectLight » et composants Vuetify (pas de CSS maison si un composant Vuetify existe) ;
- textes de l'interface en français, identiques à la maquette ;
- les 4 états de chaque écran : chargement, vide, erreur, normal ;
- version mobile (< 600 px) comme décrit dans la section Responsive ;
- accessibilité : labels sur les champs, aria-label sur les boutons icônes, contraste AA.
Ne crée pas de fonctionnalité absente de la maquette. Explique chaque fichier que tu crées,
car je suis étudiant et je dois comprendre le code.
```

**Règles de sécurité pour l'équipe** : ne colle jamais dans une IA une clé API, un mot de passe, le fichier `.env` ou des données réelles d'étudiants. Relis et teste tout le code généré avant d'ouvrir ta Pull Request.

---

## 2. Design system (Vuetify 3)

### Thème — `src/plugins/vuetify.js`

```js
import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

export default createVuetify({
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  theme: {
    defaultTheme: 'uniconnectLight',
    themes: {
      uniconnectLight: {
        dark: false,
        colors: {
          primary: '#1E4FB8', secondary: '#00796B', accent: '#F59E0B',
          error: '#C62828', warning: '#ED6C02', info: '#0288D1', success: '#2E7D32',
          background: '#F4F6FB', surface: '#FFFFFF', 'surface-variant': '#EEF2FA',
          'on-surface': '#1C2333',
        },
      },
    },
  },
  defaults: {
    VBtn: { variant: 'flat', rounded: 'sm' },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VChip: { size: 'small', variant: 'tonal' },
    VCard: { rounded: 'lg', variant: 'outlined' },
    VListItem: { rounded: 'lg' },
  },
})
```

### Couleurs et usage

| Token | Valeur | Usage |
|---|---|---|
| primary | `#1E4FB8` | Actions, liens, onglet actif, bulles de l'étudiant |
| secondary | `#00796B` | Profil enseignant (chip, avatar) |
| accent | `#F59E0B` | Mise en avant ; **Assistant IA** : icône `mdi-creation` et bouton en ambre foncé `#B45309` |
| error | `#C62828` | Suppression, erreurs, signalements |
| warning | `#ED6C02` | Expiration des messages |
| background / surface | `#F4F6FB` / `#FFFFFF` | Fond de page / cartes et barres |
| surface-variant | `#EEF2FA` | Barre de recherche, survol |
| Texte | `#1C2333` principal, `#5B6478` secondaire | |
| Bordures | `#D5DBE7` | Cartes, séparateurs |
| Tonals (chips, alertes) | primary `#E3EBFB`, warning `#FDEBD9`, error `#FBE4E4`, success `#E3F1E4`, info `#E1F2FB` | Texte foncé sur fond tonal (contraste ≥ 4,5:1) |

### Typographie — Roboto (classes Vuetify)

| Classe | Taille | Exemple |
|---|---|---|
| `text-h4` | 34/40 | Titre des pages d'authentification |
| `text-h5` | 24/32, 500 | Titre de page (« Bonjour Yassine ») |
| `text-h6` | 20/28, 500 | Titre de carte ou de dialogue |
| `text-subtitle-1` | 16 | Titre d'élément de liste |
| `text-body-2` | 14/20 | Texte courant |
| `text-button` | 14, 500, majuscules | Boutons, onglets |
| `text-caption` | 12/16 | Dates, aides (« Expire le 11/11 à 16:05 ») |
| `text-overline` | 12, 500, majuscules | Titres de sections du menu (« MES GROUPES PRIVÉS ») |

### Icônes (Material Design Icons)

`mdi-school` (logo), `mdi-home`, `mdi-bell`, `mdi-book-open-variant` (matière), `mdi-lock` (groupe privé), `mdi-pound` (channel), `mdi-bullhorn` (annonce), `mdi-compass` (explorer), `mdi-account`, `mdi-magnify`, `mdi-send`, `mdi-paperclip`, `mdi-pin`, `mdi-flag` (signaler), `mdi-download`, `mdi-creation` (IA), `mdi-timer-sand` (expiration), `mdi-shield-lock` (403), `mdi-dots-vertical` (menu d'actions).

### Composants utilisés

`v-app-bar`, `v-navigation-drawer`, `v-bottom-navigation`, `v-tabs`, `v-card`, `v-list` / `v-list-item`, `v-btn`, `v-chip`, `v-avatar` (initiales, 40 px), `v-text-field` / `v-textarea` / `v-select` (outlined), `v-switch`, `v-checkbox`, `v-dialog`, `v-menu`, `v-alert` (tonal), `v-snackbar`, `v-data-table`, `v-expansion-panels` (chapitres), `v-skeleton-loader`, `v-progress-linear`, `v-badge`.

Composants maison prévus dans Jira : `AppLayout.vue` (SCRUM-36), `EspaceList.vue` (SCRUM-86), `EmptyState.vue`, `LoadingSkeleton.vue`, composable `useNotify` (SCRUM-184), `usePolling` (SCRUM-95), `SourcesCitees.vue` (SCRUM-143).

---

## 3. Mise en page commune (`AppLayout.vue`, SCRUM-36)

- **App bar** (64 px, fond blanc, bordure basse) : bouton menu · logo « UniConnect » (+ badge « ADMIN » ambre pour l'administration) · recherche centrale en pilule (`surface-variant`) « Rechercher un espace, une personne, un fichier… » · cloche avec pastille rouge du nombre de non-lus · avatar + nom + profil (« Étudiant · L2 Info A ») · bouton déconnexion.
- **Menu latéral** (288 px, permanent dès 960 px) : bouton « Créer un groupe privé », puis Accueil, Notifications (badge), section **MES ESPACES** (classe, matières, groupes privés, channels, avec badges de non-lus), Explorer les channels, Recherche, Mon profil. Pour l'administration : section **ADMINISTRATION** (Tableau de bord, Années, Référentiels, Comptes autorisés, Utilisateurs et droits, Visibilité et stockage, Groupes organisationnels, Modération avec badge, Historique).
- **Élément actif** : fond `#E3EBFB`, texte et icône primary, police 500.
- **Contenu** : padding 24–32 px, titre `text-h5` + sous-titre secondaire, actions à droite.

### Responsive

| Largeur | Navigation | Mise en page |
|---|---|---|
| < 600 px (xs) | `v-bottom-navigation` à 5 entrées : Accueil, Espaces, Channels (Admin pour l'administration), Notifs, Profil ; app bar compacte (recherche et nom masqués) | Une colonne, onglets défilants, padding 16 px, tableaux dans un conteneur à défilement horizontal |
| 600–959 px (sm) | Drawer en mode rail (80 px) | Tablette |
| ≥ 960 px (md+) | Drawer permanent 288 px | Grilles 2 à 4 colonnes |
| ≥ 1280 px | + recherche centrale large | Panneau latéral (sources IA, épinglés) visible |

Les pages d'administration sont prévues pour ordinateur. La règle de l'équipe : chaque écran est vérifié en largeur 390 px avant d'être terminé (SCRUM-156).

---

## 4. Catalogue des écrans

Légende : **Fichier** = composant Vue à créer · **API** = routes Laravel utilisées (détail dans le ticket) · **États** = ce qui doit être affiché en plus de l'état normal.

### Authentification (mise en page en 2 colonnes : panneau bleu primary à gauche, carte de formulaire à droite ; une colonne sur mobile)

| Écran | Route | Fichier | API | États / règles | Jira |
|---|---|---|---|---|---|
| E01 Connexion | `/connexion` | `LoginView.vue` | `POST /api/login` | Erreur identifiants ; blocage après 5 échecs (15 min) ; compte suspendu | SCRUM-70 |
| E02 Création de compte | `/inscription` | `views/auth/InscriptionView.vue` | `POST /api/auth/register` | Étapes « 1 Éligibilité — 2 Activation » ; alerte info « Seules les personnes figurant sur la liste des comptes autorisés… » ; erreur sous le champ e-mail si absent de la liste ; bandeau warning si aucune année active ; écran « Vérifiez votre boîte de réception » après envoi | SCRUM-64 |
| E03 Activation | `/activation/:token` | `ActivationView.vue` | `POST /api/auth/activate` | Jauge de force (4 barres) + 4 règles (8 caractères, majuscule, minuscule, chiffre) ; bouton désactivé tant que les règles ne sont pas remplies ; état « lien expiré » avec « Renvoyer un e-mail d'activation » | SCRUM-67 |
| E01b Mot de passe oublié | `/mot-de-passe-oublie`, `/reinitialiser/:token` | `ForgotPasswordView.vue`, `ResetPasswordView.vue` | broker Laravel | Message neutre « Si un compte existe… » ; nouveau mot de passe avec les mêmes règles ; écran « Mot de passe modifié » | SCRUM-118 |

### Étudiant / enseignant

| Écran | Route | Fichier | API | États / règles | Jira |
|---|---|---|---|---|---|
| E04 Accueil | `/` | `HomeView.vue` + `EspaceList.vue` | `GET /api/me/espaces` | Cartes d'espaces (icône, nom, type, nb membres, dernier message, badge non-lus) ; « Activité récente » ; vide : « Vous n'avez encore aucun espace » | SCRUM-86 |
| E05 Espace (en-tête + onglets) | `/espaces/:id/:onglet` | `EspaceView.vue` | `GET /api/espaces/:id` | En-tête : icône, nom, chip du type, « N membres · Vous êtes propriétaire » ; onglets Discussion, Assistant IA, Annonces, Ressources, Fichiers, Épinglés, Membres, Paramètres | — |
| Discussion | `…/discussion` | `DiscussionTab.vue` | messages + `PATCH /messages/:id/epingle` | Bulles avec avatar, nom, chip « Enseignante », heure, « expire dans N j » ; menu ⋮ : Épingler (enseignant), Signaler ; pièce jointe avec barre de progression ; composer collé en bas ; carte « Résumé de la discussion » (IA) | SCRUM-95, 128, 139, 149, 178 |
| Annonces | `…/annonces` | `AnnoncesTab.vue` | annonces | Formulaire visible seulement pour l'enseignant ; vide : « Aucune annonce » | SCRUM-124 |
| Ressources | `…/ressources` | `RessourcesTab.vue` | chapitres, ressources | Chapitres en `v-expansion-panels` ; chip « Indexée pour l'IA » ; menu ⋮ Renommer / Déplacer / Supprimer (enseignant) ; lecture seule pour l'étudiant | SCRUM-127 |
| Fichiers | `…/fichiers` | `FichiersTab.vue` | `GET /api/espaces/:id/fichiers` | Tableau : fichier, partagé par, date, taille, Télécharger (lien protégé) | SCRUM-141 |
| Épinglés | `…/epingles` | `EpinglesTab.vue` | messages épinglés | Vide : « Aucun message épinglé » | SCRUM-129 |
| **Assistant IA** | `…/assistant` | `AssistantTab.vue`, `SourcesCitees.vue`, store `ia.js` | `POST /api/espaces/:id/ia/questions`, `PATCH /api/ia/echanges/:id/avis`, `POST …/ia/resume` | Bulle question à droite (primary), réponse à gauche (fond `surface-variant`) en liste à puces ; chips de sources « [1] Chapitre 3 – Normalisation.pdf · p. 14 » cliquables ; 👍/👎 ; questions suggérées ; « Recherche dans les ressources du cours… » pendant l'attente ; « Réponse générée par IA, à vérifier dans les sources » ; « Questions restantes aujourd'hui : N sur 20 » ; erreurs : sans source, **429 quota atteint**, **503 « L'assistant est momentanément indisponible »** + Réessayer ; panneau latéral « Sources indexées » | SCRUM-142, 143, 144 |
| Membres | `…/membres` | `MembresTab.vue` | API membres | Sous-onglets Membres / Invitations en attente / Demandes ; dialogue « Inviter des membres » ; « Quitter l'espace » + confirmation (masqué pour un groupe de classe) | SCRUM-91 |
| Paramètres | `…/parametres` | `ParametresTab.vue` | `PATCH /espaces/:id/assistant`, durée de visibilité | Durée de visibilité des messages ; interrupteur « Assistant IA » + consigne pour l'assistant | SCRUM-132, 147 |
| E06 Créer un espace | `/espaces/nouveau` | `CreerEspaceView.vue` | `POST /api/espaces` | Assistant en étapes : type, nom, membres à inviter | SCRUM-89 |
| E08 Channels | `/channels` | `ChannelsView.vue` | annuaire, rejoindre, demandes | Cartes ; boutons Rejoindre / Demander à rejoindre / Quitter ; chip « Demande envoyée » | SCRUM-120 |
| E09 Notifications | `/notifications` | `NotificationsView.vue` | `/api/notifications` | Non lues en gras + point bleu ; « Tout marquer comme lu » ; invitation avec Accepter / Refuser | SCRUM-122 |
| E10 Recherche | `/recherche?q=` | `RechercheView.vue` | `GET /api/recherche` | Résultats groupés : Espaces, Personnes, Fichiers, Messages ; vide : « Aucun résultat pour « … » » ; minimum 2 caractères | SCRUM-171 |
| E11 Profil | `/profil` | `ProfilView.vue` | API profil | Onglets Informations (champs grisés en lecture seule + bloc « Mes droits » en chips), Sécurité (changer le mot de passe), Notifications (interrupteurs application / e-mail, résumé quotidien) | SCRUM-191 |
| E00 Erreurs | `/403`, route `*` | `ForbiddenView.vue`, `NotFoundView.vue` | — | Icône dans un cercle, code (« Erreur 403 · Accès refusé »), titre, explication, bouton « Retour à l'accueil » | SCRUM-184 |

### Administration (badge « ADMIN », menu ADMINISTRATION)

| Écran | Route | Fichier | API | États / règles | Jira |
|---|---|---|---|---|---|
| A09 Tableau de bord | `/admin` | `AdminDashboardView.vue` | `GET /api/admin/tableau-de-bord` | 4 cartes KPI (utilisateurs actifs, espaces, messages, stockage) + comptes non activés (taux d'activation) + invitations en attente ; barres « messages par semaine » ; barres horizontales par profil ; alertes | SCRUM-169 |
| A01 Années | `/admin/annees` | `AnneesView.vue` | `/api/admin/annees` | Une seule année « Active » ; « En préparation » / « Archivée » | SCRUM-73 |
| A02 Référentiels | `/admin/referentiels` | `ReferentielsView.vue` | CRUD référentiels | Onglets : départements, filières, niveaux, classes, matières | SCRUM-80 |
| A03 Comptes autorisés | `/admin/comptes` | `ComptesView.vue` | `/api/admin/comptes-autorises` | Filtres Tous / Activés / Non activés ; dialogue d'import CSV avec aperçu des erreurs (ligne + motif) avant validation ; Relancer | SCRUM-77 |
| A04 Utilisateurs et droits | `/admin/droits` | `DroitsView.vue` | API utilisateurs et droits | Liste à gauche, détail à droite avec 7 cases à cocher de droits ; « Suspendre » → dialogue durée + **motif obligatoire** ; chip « Suspendu jusqu'au … » | SCRUM-167 |
| A05 Visibilité et stockage | `/admin/visibilite` | `VisibiliteView.vue` | API règles | Durée par type d'espace ; taille maximale d'un fichier | SCRUM-190 |
| A06 Groupes organisationnels | `/admin/groupes` | `GroupesView.vue` | génération | Aperçu de la génération avant de la lancer | SCRUM-83 |
| A07 Modération | `/admin/moderation` | `ModerationView.vue` | décisions | Carte par signalement : motif, espace, date, message cité ; Conserver / Avertir l'auteur / Supprimer le message | SCRUM-151 |
| A08 Historique | `/admin/historique` | `HistoriqueView.vue` | opérations | Tableau date, auteur, action, détail ; export CSV | SCRUM-135 |

---

## 5. Règles transverses

- **Textes** : français, vouvoiement, messages d'erreur qui disent quoi faire (« Utilisez votre adresse @univ.tn »).
- **Confirmation** avant toute action destructive (supprimer, quitter, suspendre) avec `v-dialog`, bouton rouge à droite.
- **Retour d'action** : `v-snackbar` en bas (« Signalement envoyé à la modération »).
- **Chargement** : `v-skeleton-loader` à la place du contenu ; jamais d'écran blanc.
- **Droits** : un bouton réservé (Épingler, Nouvelle annonce, Inviter, menus admin) n'est **pas affiché** si l'utilisateur n'a pas le droit ; le serveur vérifie quand même (policies Laravel → 403).
- **Dates** : format français `dd/MM/yyyy` et « il y a 2 h ».
- **Données de démo** des maquettes : Yassine Ben Salah (étudiant, L2 Info A), Mme Sarra Ben Ali (enseignante), Leila Haddad (administration), espace « Bases de données · L2 Info A ».
