# UniConnect

Application de communication universitaire : espaces de classe et de matière créés automatiquement, groupes privés, annonces, ressources de cours, et un **Assistant IA** qui répond uniquement à partir des supports des enseignants, en citant la page.

Projet étudiant 2026/2027 · **v1.0 livrée et en production** (démonstration le 19/11/2026).

📄 **Cahier des charges de la version déployée : [`docs/cahier-des-charges.md`](docs/cahier-des-charges.md)** (version PDF : [`docs/cahier-des-charges.pdf`](docs/cahier-des-charges.pdf)).

📚 **Documentation complète : [`docs/`](docs/README.md)**. On y trouve l'architecture, le contrat d'API, le schéma SQL, les diagrammes, le déploiement, le plan de tests, la démo et le guide utilisateur. Ce README couvre seulement l'installation locale.

## Présentation

UniConnect est composé de deux applications :

| Dossier | Contenu |
|---|---|
| `backend/` | API Laravel + Sanctum, PostgreSQL |
| `frontend/` | Application Vue 3 + Vuetify + Pinia |

Le backend expose une route de santé (`GET /api/health`) qui vérifie aussi la connexion à PostgreSQL. Le frontend consomme l'API via l'URL définie dans `frontend/.env`.

## En production

| | URL |
|---|---|
| Application (Vercel) | <https://uniconnect-six-lake.vercel.app> |
| API (Render) | <https://uniconnect-api-qov1.onrender.com> |
| Santé de l'API | <https://uniconnect-api-qov1.onrender.com/api/health> (doit répondre `"db": "ok"`) |

## Vidéo de démonstration

▶️ **Vidéo** : lien ajouté après l'enregistrement (voir aussi le scénario [`docs/demo.md`](docs/demo.md) et le pas à pas [`docs/demo-pas-a-pas.md`](docs/demo-pas-a-pas.md)).

## Fonctionnalités de la v1.0 (en production)

| Profil | Fonctionnalités |
|---|---|
| **Visiteur** | Inscription contrôlée (matricule + e-mail de la liste de la scolarité), e-mail d'activation (lien signé 48 h), connexion (blocage 15 min après 5 échecs) |
| **Étudiant** | Accueil avec non-lus, discussions de classe et de matière, pièces jointes, messages qui expirent selon la règle du groupe, annonces, notifications, ressources de cours, groupes privés et invitations, **Assistant IA** avec sources citées (20 questions/jour) |
| **Enseignant** | Annonces (avec e-mail optionnel), chapitres et ressources, indexation automatique des PDF pour l'IA, activation et consigne de l'Assistant IA, gestion des membres |
| **Administration** | Années universitaires (une seule active), référentiels, comptes autorisés avec **import Excel** (aperçu des erreurs), **génération automatique des groupes** de classe et de matière, synchronisation, archivage |

Prévu en v1.1 (masqué en v1.0) : mot de passe oublié, recherche globale, profil, channels, modération, tableau de bord d'administration. Détail : [cahier des charges § 10](docs/cahier-des-charges.md).

## Technologies et services

| Partie | Technologies |
|---|---|
| Backend | PHP 8.4, Laravel 13, Sanctum, PostgreSQL 16 (recherche plein texte `tsvector`), maatwebsite/excel, smalot/pdfparser |
| Frontend | Vue 3, Vuetify 3, Pinia, Vue Router, Axios, Vite |
| Assistant IA | RAG : recherche PostgreSQL dans les PDF du cours, puis **Gemini** (Groq en secours) |
| Tests et qualité | PHPUnit, Pint, Vitest, oxlint, ESLint, GitHub Actions (CI sur chaque pull request) |
| Local | Docker Compose (PostgreSQL + Mailpit), Bruno |

| Service (offres gratuites) | Rôle |
|---|---|
| [Vercel](https://vercel.com) | Hébergement du frontend |
| [Render](https://render.com) | API Laravel en Docker |
| [Neon](https://neon.tech) | Base PostgreSQL |
| [Supabase Storage](https://supabase.com) | Fichiers (bucket privé compatible S3) |
| [Resend](https://resend.com) | E-mails d'activation et d'annonce |
| [Google AI Studio](https://aistudio.google.com) / [Groq](https://groq.com) | Modèles d'IA |
| [Jira](https://uniconnectteam26.atlassian.net/jira/software/projects/SCRUM/boards/1) / GitHub | Scrum, code, revues, CI |

Fonctionnement détaillé : [`docs/architecture.md`](docs/architecture.md) · déploiement : [`docs/deploiement.md`](docs/deploiement.md).

## Équipe

Chahredine Baccouche · Mansour Naili · Moataz Rabia · Mouetez Bouajila · Hayder Ramma · Ayoub Boussoukaya — encadrant : M. Rabii Mejai.

## Liens

- **Code source** : <https://github.com/ChahredineBACCOUCH/uniconnect>
- **Jira** : <https://uniconnectteam26.atlassian.net/jira/software/projects/SCRUM/boards/1>
- **Cahier des charges** : [`docs/cahier-des-charges.md`](docs/cahier-des-charges.md)
- **Maquette** : [`docs/maquette/`](docs/maquette/)
- **Documentation complète** : [`docs/`](docs/README.md) (architecture, API, conception, déploiement, tests, démo, guide utilisateur)
- **Collection Bruno** (tests manuels de l'API) : [`docs/api/`](docs/api/)

## Prérequis

| Outil | Version requise | Vérification |
|---|---|---|
| PHP | **8.4.1 ou plus** | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | **22.22+ ou 24.15+** (LTS recommandée) | `node -v` |
| npm | fourni avec Node.js | `npm -v` |
| Docker Desktop + Docker Compose v2 | version récente | `docker compose version` |
| Git | 2.x | `git --version` |

> **Important :** PHP 8.3 et Node.js 20 ne suffisent pas pour ce dépôt.
> `backend/composer.lock` contient des paquets Symfony 8.1 qui exigent PHP `>= 8.4.1`, et `frontend/package.json` impose Node `^22.18.0 || >=24.12.0`.
> Utilisez PHP 8.4.1+ et une version LTS de Node.js (22 ou 24). Node.js 26 est actuellement déconseillé : les tests Vitest échouent à cause de `localStorage`.

Extensions PHP nécessaires (à activer dans `php.ini` si besoin) : `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip` (import Excel) et `pdo_sqlite` (pour les tests).

```bash
php -m
```

Vérifiez notamment que `pdo_pgsql` apparaît dans la liste.

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/ChahredineBACCOUCH/uniconnect.git
cd uniconnect
```

### 2. Démarrer PostgreSQL et Mailpit

Docker Desktop doit être lancé avant cette étape.

```bash
docker compose up -d
docker compose ps
```

| Service | Port(s) | Usage |
|---|---|---|
| `postgres` | `5433` | Base `uniconnect` (utilisateur `uniconnect`, mot de passe `secret`) |
| `mailpit` | `1025` (SMTP), `8025` (interface web) | Réception des e-mails en local |

Attendez que le service `postgres` soit indiqué comme `healthy` dans `docker compose ps` avant de lancer les migrations.

### 3. Installer et lancer le backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

> Sous Windows/PowerShell, si `cp` n'est pas disponible, utilisez `Copy-Item .env.example .env`.

Le fichier `backend/.env.example` est déjà configuré pour la base PostgreSQL et Mailpit de `docker-compose.yml`.

Le seeder remplit la base avec des données de démonstration : année 2026/2027, référentiel académique, 40 comptes autorisés et 3 comptes déjà activés. Voir la section [Comptes de démo](#comptes-de-démo).

Le backend tourne sur <http://localhost:8000>. Laissez ce terminal ouvert.

### 4. Installer et lancer le frontend

Dans un **second terminal**, depuis la racine du dépôt :

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

> Sous Windows/PowerShell : `Copy-Item .env.example .env`.

Le frontend tourne sur <http://localhost:5173>. Par défaut, `frontend/.env` contient :

```dotenv
VITE_API_URL=http://localhost:8000/api
```

Si vous changez le port du backend, mettez aussi à jour cette valeur puis relancez `npm run dev`.

### 5. (Optionnel) Données complètes de la démo et Assistant IA

Pour retrouver en local les données de la démonstration (cours « Bases de données » avec 3 PDF indexés, discussion, annonces, groupe privé, invitation) :

```bash
cd backend
php artisan db:seed --class=DemoProductionSeeder
```

Pour que l'**Assistant IA** réponde, ajoutez dans `backend/.env` une clé gratuite Gemini (et Groq en secours), puis `php artisan config:clear` :

```dotenv
GEMINI_API_KEY=votre_cle_gemini
GROQ_API_KEY=votre_cle_groq
```

Les clés se créent sur <https://aistudio.google.com/apikey> et <https://console.groq.com/keys> (détails : [`docs/installation.md`](docs/installation.md) § 4.1). Sans clé, l'Assistant répond « momentanément indisponible ».

Les PDF ajoutés depuis l'interface sont indexés en arrière-plan : lancez le worker dans un **troisième terminal** :

```bash
cd backend
php artisan queue:work
```

### 6. Vérifier que tout fonctionne

1. Ouvrez <http://localhost:8000/api/health> : la réponse doit contenir `"status": "ok"` et `"db": "ok"`.
2. Ouvrez <http://localhost:5173> : l'application frontend doit s'afficher.
3. Ouvrez <http://localhost:8025> : l'interface Mailpit doit être accessible. En local, **tous les e-mails** (activation, annonces) arrivent ici.
4. Connectez-vous sur <http://localhost:5173> avec un compte de démo (ci-dessous).

**Résumé (3 terminaux)** : `docker compose up -d` → `cd backend && php artisan serve` → `cd frontend && npm run dev` (+ `php artisan queue:work` pour l'indexation des PDF).

## Comptes de démo

`php artisan migrate:fresh --seed` exécute `DemoSeeder` et crée trois comptes déjà activés. Ils utilisent tous le mot de passe **`Demo1234!`**.

| Profil | E-mail | Mot de passe |
|---|---|---|
| Étudiant | `etudiant@demo.uniconnect` | `Demo1234!` |
| Enseignant | `enseignant@demo.uniconnect` | `Demo1234!` |
| Administration | `admin@demo.uniconnect` | `Demo1234!` |

Le compte administration possède tous les droits : `GERER_PARAMETRAGE`, `GERER_UTILISATEURS`, `CREER_GROUPE_ORGA`, `CREER_GROUPE_PRIVE`, `CREER_CHANNEL`, `MODERER` et `CONSULTER_TABLEAU_BORD`.

Le seeder crée également 40 entrées dans `comptes_autorises` : 35 étudiants répartis dans les 3 classes (L2 Info A, L2 Info B, L3 Info), 4 enseignants et 1 membre de l'administration. Les 3 comptes ci-dessus sont activés ; les 37 autres restent à l'état `NON_CREE`.

## Commandes utiles

### Backend

À lancer depuis `backend/`.

| Commande | Rôle |
|---|---|
| `php artisan test` | Lance les tests PHPUnit (base SQLite en mémoire). |
| `vendor/bin/pint` | Formate le code PHP avec Pint. |
| `vendor/bin/pint --test` | Vérifie le formatage sans modifier les fichiers. |

> Sous Windows/PowerShell, utilisez `php vendor/bin/pint` (ou `php vendor/bin/pint --test`) si `vendor/bin/pint` n'est pas reconnu.

### Frontend

À lancer depuis `frontend/`.

| Commande | Rôle |
|---|---|
| `npm run lint` | Lance oxlint puis ESLint. **Corrige automatiquement** les fichiers. |
| `npm run test` | Lance les tests Vitest une seule fois. |
| `npm run check:lint` | Même vérification que `lint`, **sans rien modifier** (c'est ce que lance la CI). |
| `npm run test:unit` | Lance Vitest en mode watch. |
| `npm run build` | Construit l'application pour la production. |
| `npm run dev` | Lance le serveur de développement Vite. |

### Docker

À lancer depuis la racine du dépôt.

| Commande | Rôle |
|---|---|
| `docker compose up -d` | Démarre PostgreSQL et Mailpit. |
| `docker compose ps` | Affiche l'état des services. |
| `docker compose logs -f postgres` | Affiche les logs PostgreSQL. |
| `docker compose down` | Arrête les services (les données restent dans le volume). |

## Conventions

- Branche par ticket, créée depuis `main` : `feat/SCRUM-xx-description`.
- Commits : `SCRUM-xx: message` (message court et à l'impératif).
- Pas de push direct sur `main` : Pull Request obligatoire avec **1 relecture** avant fusion.
- La CI GitHub Actions (`.github/workflows/`) lance sur chaque PR les jobs **backend** (Pint + tests sur PostgreSQL 16) et **frontend** (lint, build, Vitest). Les deux doivent être verts pour fusionner.
- La branche doit être à jour avec `main` (bouton **Update branch**). Une mise à jour annule l'approbation : il faut la redemander. On fusionne uniquement avec **Squash and merge**.
- Aucun secret dans Git : les clés vont dans `.env` (ignoré) ; seul `.env.example` est versionné.
- Ne jamais committer `vendor/`, `node_modules/`, `dist/` ni les fichiers `.env`.
- Avant d'ouvrir une PR : lancer les tests et le lint (`php artisan test`, `vendor/bin/pint --test`, `npm run check:lint`, `npm run test`).

## Dépannage

| Problème | Solution |
|---|---|
| `docker compose` introuvable | Installez Docker Desktop et démarrez-le. |
| `could not find driver` ou erreur PostgreSQL | Activez l'extension `pdo_pgsql` dans `php.ini`, puis redémarrez le terminal. |
| `composer install` échoue sur `symfony/* requires php >=8.4.1` | Passez à PHP 8.4.1 ou plus. N'utilisez pas `--ignore-platform-req`. |
| `SQLSTATE[08006]` / connexion refusée | Attendez que PostgreSQL soit `healthy` (`docker compose ps`) puis relancez `php artisan migrate --seed`. |
| `authentification par mot de passe échouée pour l'utilisateur « uniconnect »` | Un PostgreSQL installé sur Windows (service `postgresql-x64-16`) occupe le port 5432. Le conteneur est donc publié sur `5433` par défaut (`DB_PORT=5433` dans `backend/.env`). Si votre `backend/.env` date d'avant ce changement, mettez `DB_PORT=5433` ; pour revenir au 5432 : `"5432:5432"` dans `docker-compose.yml` et `DB_PORT=5432` dans `backend/.env`. Voir le commentaire de SCRUM-25. |
| Port `8000` ou `5173` déjà utilisé | Arrêtez le processus concerné, ou utilisez `php artisan serve --port=8001` et adaptez `VITE_API_URL`. |
| Les tests frontend échouent sur `localStorage` | Utilisez Node.js 22 ou 24 (Node 26 est concerné). |

Suivi : Jira [uniconnectteam26](https://uniconnectteam26.atlassian.net/jira/software/projects/SCRUM/boards/1)
