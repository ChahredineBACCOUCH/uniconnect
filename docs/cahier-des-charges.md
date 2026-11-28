# Cahier des charges — UniConnect v1.0 (version déployée)

| Élément | Détail |
|---|---|
| **Projet** | UniConnect, plateforme de communication universitaire avec Assistant IA |
| **Version décrite** | **v1.0**, version en production |
| **Cadre** | Projet étudiant, année universitaire 2026/2027 |
| **Encadrant** | M. Rabii Mejai |
| **Équipe** | Chahredine Baccouche, Mansour Naili, Moataz Rabia, Mouetez Bouajila, Hayder Ramma, Ayoub Boussoukaya |
| **Date du document** | 28/11/2026 |

Ce document décrit **ce qui est livré et en ligne** : il sert de référence pour l'évaluation. Les fonctions prévues mais non livrées sont listées à part (§ 10, v1.1).

---

## 1. Liens du projet

| Élément | Lien |
|---|---|
| Application en ligne | <https://uniconnect-six-lake.vercel.app> |
| API (Render) | <https://uniconnect-api-qov1.onrender.com/api> |
| État de l'API | <https://uniconnect-api-qov1.onrender.com/api/health> (réponse attendue : `"status": "ok"`, `"db": "ok"`) |
| Code source (GitHub) | <https://github.com/ChahredineBACCOUCH/uniconnect> |
| Gestion de projet (Jira) | <https://uniconnectteam26.atlassian.net/jira/software/projects/SCRUM/boards/1> |
| Documentation | [`docs/`](README.md) dans le dépôt |
| Installation en local | [`README.md`](../README.md) à la racine du dépôt |
| Vidéo de démonstration | <https://github.com/ChahredineBACCOUCH/uniconnect/releases/download/v1.0/uniconnect-demo-v1.0.webm> (release [v1.0](https://github.com/ChahredineBACCOUCH/uniconnect/releases/tag/v1.0)) |

**Comptes de démonstration** (mot de passe `Demo1234!`) :

| Profil | E-mail |
|---|---|
| Étudiant (Amine Benali, L2 Informatique A) | `etudiant@demo.uniconnect` |
| Enseignante (Sophie Martin, Bases de données) | `enseignant@demo.uniconnect` |
| Administration (Fatou Diallo) | `admin@demo.uniconnect` |

> L'API est hébergée sur une offre gratuite : après 15 minutes sans visite, le serveur s'endort. La première requête prend alors environ une minute ; l'application affiche « Le serveur se réveille ».

---

## 2. Contexte et problème

Dans une université, la communication est dispersée : e-mails, groupes WhatsApp, plateformes pédagogiques, affichage. Les étudiants manquent des annonces, les supports de cours sont éparpillés, et rien ne garantit que seules les bonnes personnes ont accès aux échanges d'une classe.

## 3. Objectifs

1. **Réunir** les échanges de chaque classe et de chaque cours dans un espace unique.
2. **Créer automatiquement** les espaces de classe et de matière à partir des données de la scolarité.
3. **Contrôler l'accès** : seules les personnes déclarées par la scolarité peuvent créer un compte ; chaque espace n'est visible que par ses membres.
4. **Aider les étudiants** avec un Assistant IA qui répond **uniquement à partir des supports de l'enseignant**, en citant la source et la page.
5. Fonctionner **sur ordinateur et sur téléphone**, avec un hébergement **gratuit**.

---

## 4. Acteurs

| Acteur | Description | Droits principaux |
|---|---|---|
| **Visiteur** | Personne non connectée | Créer son compte (si autorisée), activer, se connecter |
| **Étudiant** | Rattaché à une classe | Lire et écrire dans ses espaces, télécharger les supports, créer un groupe privé, poser des questions à l'Assistant IA |
| **Enseignant** | Responsable d'une ou plusieurs matières | Comme l'étudiant, plus : publier des annonces, déposer des ressources, gérer les membres de ses espaces, activer l'Assistant IA |
| **Administration** | Personnel de la scolarité | Années universitaires, référentiels, comptes autorisés (dont import Excel), génération des groupes organisationnels |

Les droits sont **vérifiés côté serveur** (Policies et Gates Laravel), pas seulement dans l'interface.

---

## 5. Besoins fonctionnels livrés (v1.0)

Statut : ✅ livré et déployé.

### 5.1 Comptes et accès

| Réf. | Exigence | Statut |
|---|---|---|
| F01 | **Inscription contrôlée** : matricule + e-mail doivent figurer dans la liste des comptes autorisés de l'année active ; un seul compte par matricule ; refus expliqué en français (plateforme fermée, non autorisé, déjà inscrit). | ✅ |
| F02 | **E-mail d'activation** avec lien signé valable 48 h, utilisable une seule fois ; bouton « Renvoyer l'e-mail d'activation ». | ✅ |
| F03 | **Activation** : choix du mot de passe (8 caractères minimum, une majuscule, un chiffre), connexion directe, rattachement automatique aux groupes de sa classe et de ses matières. | ✅ |
| F04 | **Connexion / déconnexion** par e-mail et mot de passe ; blocage 15 minutes après 5 échecs ; compte suspendu ou désactivé refusé. | ✅ |

### 5.2 Administration

| Réf. | Exigence | Statut |
|---|---|---|
| F05 | **Années universitaires** : créer, modifier, activer ; **une seule année active** ; archivage optionnel des groupes de l'année précédente. | ✅ |
| F06 | **Référentiels** : départements, filières, niveaux, classes, matières (création, modification, désactivation). | ✅ |
| F07 | **Comptes autorisés** : liste paginée, recherche, filtres, ajout, modification, désactivation. | ✅ |
| F08 | **Import Excel** des comptes autorisés : modèle téléchargeable, aperçu ligne par ligne (valides, doublons, erreurs expliquées) **sans rien écrire**, puis import des seules lignes valides. | ✅ |
| F09 | **Génération automatique des groupes organisationnels** : un groupe par classe, un groupe par matière ; aperçu ; relance sans doublon ; **synchronisation** d'un groupe ; archivage. | ✅ |

### 5.3 Espaces et membres

| Réf. | Exigence | Statut |
|---|---|---|
| F10 | **Accueil** : mes espaces (classe, matières, groupes privés) avec le nombre de messages non lus. | ✅ |
| F11 | **Groupe privé** : création par un étudiant ou un enseignant, choix de la durée de visibilité des messages. | ✅ |
| F12 | **Invitations** : inviter des personnes (recherche), accepter ou refuser ; expiration après 14 jours. | ✅ |
| F13 | **Membres** : liste, rôles (propriétaire, co-gestionnaire, membre), retirer, transférer la propriété, quitter. Un retrait coupe l'accès immédiatement. | ✅ |

### 5.4 Communication

| Réf. | Exigence | Statut |
|---|---|---|
| F14 | **Discussion** de groupe : envoyer, répondre, modifier et supprimer ses messages ; nouveaux messages affichés automatiquement (toutes les 5 s). | ✅ |
| F15 | **Pièces jointes** : jusqu'à 5 fichiers de 25 Mo par message (PDF, images, Office, ZIP) ; aperçu des images. | ✅ |
| F16 | **Expiration des messages** selon la règle du groupe (24 h, 7 jours, 30 jours ou illimitée), fixée à l'envoi. | ✅ |
| F17 | **Annonces** officielles par les gestionnaires, avec pièces jointes et envoi optionnel par e-mail. | ✅ |
| F18 | **Notifications** dans l'application (annonce, invitation, ressource…) avec compteur de non-lues. | ✅ |

### 5.5 Ressources pédagogiques et Assistant IA

| Réf. | Exigence | Statut |
|---|---|---|
| F19 | **Ressources** : chapitres ordonnés, fichiers ou liens ; renommer, déplacer, supprimer ; téléchargement réservé aux membres. | ✅ |
| F20 | **Indexation automatique** des PDF pour l'IA (en arrière-plan), badge « Indexé pour l'IA ». | ✅ |
| F21 | **Activation de l'Assistant IA** par l'enseignant gestionnaire, avec une consigne optionnelle. | ✅ |
| F22 | **Questions à l'Assistant IA** : réponse rédigée **uniquement** à partir des supports du groupe, avec **sources citées** (document et page) cliquables. | ✅ |
| F23 | **Réponse honnête** : si le cours ne contient pas la réponse, l'assistant le dit au lieu d'inventer. | ✅ |
| F24 | **Quota** de 20 questions par jour et par personne ; **avis** 👍/👎 sur chaque réponse. | ✅ |
| F25 | **Continuité** : bascule automatique de Gemini vers Groq en cas de panne ou de quota atteint. | ✅ |

---

## 6. Règles de gestion principales

| Réf. | Règle |
|---|---|
| R01 | Une seule année universitaire active à la fois (garantie par un index unique en base). |
| R02 | Seules les personnes de la liste des comptes autorisés de l'année active peuvent s'inscrire. |
| R03 | Un étudiant actif est toujours rattaché à une classe. |
| R04 | Un espace n'est accessible (messages, fichiers, ressources, IA) qu'à ses membres, vérifié **en base à chaque requête**. |
| R05 | On n'invite pas dans un groupe de classe ou de matière : ses membres viennent des référentiels. |
| R06 | Un espace a toujours un propriétaire. |
| R07 | La date d'expiration d'un message est fixée à l'envoi ; une pièce jointe expire avec son message. |
| R08 | Un groupe archivé est en lecture seule. |
| R09 | L'Assistant IA ne cherche que dans les ressources de l'espace et n'envoie à l'IA que des extraits de cours (aucune donnée personnelle). |

---

## 7. Besoins non fonctionnels

| Domaine | Exigence | Comment c'est respecté |
|---|---|---|
| **Sécurité** | Mots de passe protégés | Hachage bcrypt, jamais stockés en clair |
| | Authentification | Jeton Sanctum dans l'en-tête `Authorization` |
| | Contrôle d'accès | Policies et middleware côté serveur ; 403 même avec un lien direct |
| | Anti-abus | Limitation des requêtes (inscription 10/min, renvoi d'e-mail 5/min), blocage après 5 échecs de connexion |
| | Fichiers | Stockage objet **privé**, noms aléatoires, téléchargement uniquement via l'API |
| | Secrets | Variables d'environnement (Render, Vercel), jamais dans Git |
| **Ergonomie** | Interface en français, messages d'erreur clairs | Validation avec messages par champ |
| **Mobile** | Utilisable sur téléphone | Mise en page responsive, navigation en bas d'écran, dialogues plein écran |
| **Accessibilité** | Contrastes, navigation clavier | Lien « Aller au contenu », contrastes renforcés ; Lighthouse accessibilité 100 |
| **Disponibilité** | Pas de coupure lors d'une mise à jour | Render ne bascule qu'après réponse de `/api/health` ; retour arrière en un clic |
| **Qualité** | Code testé | ≈ 360 tests backend (PHPUnit) et ≈ 275 tests frontend (Vitest), lancés automatiquement à chaque pull request |
| **Coût** | Hébergement gratuit | Offres gratuites de Vercel, Render, Neon, Supabase, Resend, Gemini et Groq |

---

## 8. Architecture technique

```
Navigateur (PC ou téléphone)
   │ HTTPS
   ▼
Frontend Vue 3 + Vuetify ─── hébergé sur Vercel
   │ API REST JSON + jeton Bearer (Sanctum)
   ▼
API Laravel 13 (PHP 8.4) ─── image Docker sur Render
   ├── PostgreSQL 16 (Neon) : données + recherche plein texte française
   ├── Supabase Storage (compatible S3, privé) : fichiers
   ├── Gemini, puis Groq en secours : Assistant IA
   └── Resend (API HTTP) : e-mails
```

Dans le conteneur Render, Supervisor fait tourner : **nginx** (serveur web), **php-fpm** (Laravel), un **worker** de file d'attente (indexation des PDF, e-mails) et le **planificateur** (expiration des invitations chaque nuit).

---

## 9. Technologies et outils utilisés

### 9.1 Développement

| Partie | Technologies |
|---|---|
| Backend | PHP 8.4, **Laravel 13**, Laravel Sanctum, Eloquent, Policies, files d'attente (driver `database`), maatwebsite/excel (import Excel), smalot/pdfparser (lecture des PDF), resend/resend-php |
| Frontend | **Vue 3** (Composition API), **Vuetify 3**, Pinia, Vue Router, Axios, Vite |
| Base de données | **PostgreSQL 16** (`tsvector` + index GIN pour la recherche, index uniques partiels) ; SQLite pour les tests locaux |
| IA | **Gemini** (Google) en principal, **Groq** (modèle `gpt-oss`) en secours, technique **RAG** |
| Tests et qualité | PHPUnit, Laravel Pint, **Vitest**, oxlint, ESLint, Prettier, Lighthouse |
| Local | Docker Compose (PostgreSQL + Mailpit), Bruno (tests manuels de l'API) |

### 9.2 Hébergement et services (offres gratuites)

| Service | Usage |
|---|---|
| **Vercel** | Frontend (build Vite, CDN) |
| **Render** | API Laravel dans une image Docker (région Francfort) |
| **Neon** | Base PostgreSQL gérée |
| **Supabase Storage** | Fichiers (bucket privé compatible S3) |
| **Resend** | Envoi des e-mails (API HTTP, le SMTP étant bloqué par Render) |
| **Google AI Studio (Gemini)** / **Groq** | Modèles d'IA |

### 9.3 Gestion de projet et collaboration

| Outil | Usage |
|---|---|
| **Jira** (Scrum) | Backlog, epics, user stories, sprints, suivi des tickets `SCRUM-xx` |
| **GitHub** | Code source, pull requests avec relecture obligatoire, fusion « Squash and merge » |
| **GitHub Actions** | Intégration continue : formatage, tests backend sur PostgreSQL, lint, build et tests frontend, construction de l'image Docker |
| PlantUML, Mermaid | Diagrammes de cas d'utilisation, de classes et d'architecture |

---

## 10. Hors périmètre de la v1.0 (prévu en v1.1)

Ces fonctions sont **masquées** dans la version déployée et planifiées dans Jira :

- mot de passe oublié ;
- recherche globale, page de profil ;
- annuaire et création de channels ;
- onglets Fichiers et Épinglés ;
- demandes d'adhésion, signalement et modération ;
- création manuelle de groupe organisationnel ;
- tableau de bord d'administration ;
- résumé de discussion par l'IA.

---

## 11. Contraintes

| Contrainte | Conséquence |
|---|---|
| Hébergement **gratuit** | Serveur qui s'endort après 15 min ; pas de WebSocket (rafraîchissement toutes les 5 s) ; quotas d'IA limités (20 questions/jour) |
| SMTP bloqué par Render | Envoi des e-mails par l'API HTTP de Resend ; sans domaine vérifié, seule l'adresse du compte Resend reçoit les e-mails |
| Délai | Livraison avancée d'une semaine : priorité à un cœur complet et testé |
| Données personnelles | Seuls des extraits de cours sont envoyés à l'IA |

---

## 12. Organisation

- **Méthode** : Scrum, sprints suivis dans Jira, revues de code croisées.
- **Règles Git** : une branche par ticket, pull request obligatoire relue par un autre membre, CI verte avant fusion, aucun secret dans le dépôt.
- **Déploiement** : automatique à chaque fusion dans `main` (Vercel pour le frontend, Render pour l'API ; migrations appliquées au démarrage).

| Membre | Domaine principal |
|---|---|
| Chahredine Baccouche | Espaces, membres et invitations ; déploiement et intégration |
| Moataz Rabia | Inscription, activation, connexion |
| Mouetez Bouajila | Discussions et pièces jointes |
| Hayder Ramma | Ressources, chapitres, groupes organisationnels |
| Mansour Naili | Annonces et administration |
| Ayoub Boussoukaya | Assistant IA |

---

## 13. Livrables

| Livrable | Où |
|---|---|
| Application déployée | <https://uniconnect-six-lake.vercel.app> |
| Code source | Dépôt GitHub |
| Ce cahier des charges | `docs/cahier-des-charges.md` (et sa version PDF) |
| Installation en local | `README.md` |
| Architecture, API, conception (schéma SQL, diagrammes) | `docs/architecture.md`, `docs/api.md`, `docs/conception/` |
| Déploiement et retour arrière | `docs/deploiement.md` |
| Plans de tests | `docs/tests.md`, `docs/plan-de-tests.md` |
| Guide utilisateur | `docs/guide-utilisateur.md` |
| Scénario et vidéo de démonstration | `docs/demo.md`, `docs/demo-pas-a-pas.md`, vidéo (lien dans le README) |
| Suivi Scrum | Tableau Jira |

---

## 14. Critères d'acceptation

La version est acceptée si :

1. `/api/health` répond `"status": "ok"` et `"db": "ok"` ;
2. les trois comptes de démonstration se connectent et voient leurs espaces ;
3. une inscription non autorisée est refusée et une inscription autorisée reçoit l'e-mail d'activation ;
4. un non-membre reçoit 403 sur les messages, fichiers et ressources d'un espace ;
5. l'Assistant IA répond à une question du cours avec une source citée, et dit ne pas savoir pour une question hors sujet ;
6. la CI GitHub est verte sur `main`.
