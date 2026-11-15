# Installation locale (développement)

Ce document reprend et complète l'installation du [README principal](../README.md). Il est la référence pour réinstaller le projet **de zéro** (c'est le critère de livraison de SCRUM-186).

## 1. Prérequis

| Outil | Version requise | Vérification |
|---|---|---|
| PHP | **8.4.1 ou plus** | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | **22.22+ ou 24.15+** (LTS recommandée) | `node -v` |
| npm | fourni avec Node.js | `npm -v` |
| Docker Desktop + Docker Compose v2 | version récente | `docker compose version` |
| Git | 2.x | `git --version` |

> **Important :** PHP 8.3 et Node.js 20 ne suffisent pas. `backend/composer.lock` contient des paquets Symfony 8.1 qui exigent PHP `>= 8.4.1`, et `frontend/package.json` impose Node `^22.18.0 || >=24.12.0`. Node.js 26 casse les tests Vitest (problème `localStorage`) : utilisez Node 22 ou 24.

Extensions PHP nécessaires (à activer dans `php.ini` si besoin) : `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip` (import Excel) et `pdo_sqlite` (pour les tests).

```bash
php -m
```

Vérifiez notamment que `pdo_pgsql` et `gd` apparaissent dans la liste. Sous Windows avec Laragon, activez-les dans `C:\laragon\bin\php\php-8.4.x\php.ini` (ou le `php.ini` de votre installation) puis redémarrez le terminal.

## 2. Cloner le dépôt

```bash
git clone https://github.com/ChahredineBACCOUCH/uniconnect.git
cd uniconnect
```

## 3. Démarrer PostgreSQL et Mailpit

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

## 4. Installer et lancer le backend

```bash
cd backend
composer install
cp .env.example .env        # Windows PowerShell : Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Le backend tourne sur <http://localhost:8000>. Laissez ce terminal ouvert.

Le seeder remplit la base avec des données de démonstration : année 2026/2027, référentiel académique, 40 comptes autorisés et 3 comptes déjà activés (voir § 6).

### 4.1 Clés gratuites pour l'Assistant IA (Gemini et Groq)

L'Assistant IA utilise **Gemini** (principal) puis **Groq** (secours) — deux offres **gratuites**, sans carte bancaire. Sans ces clés, l'Assistant répond **503**.

1. **Gemini** — créez une clé sur <https://aistudio.google.com/apikey> (compte Google). Modèle par défaut : `gemini-3.5-flash-lite` (15 requêtes/min, 500/jour ; les modèles « Flash » sont limités à 20/jour).
2. **Groq** — créez un compte et une clé sur <https://console.groq.com>. Modèle par défaut : `openai/gpt-oss-120b` (1 000 requêtes/jour, 8 000 tokens/min).

Renseignez-les dans `backend/.env` :

```dotenv
LLM_PROVIDER=gemini
GEMINI_API_KEY=votre_cle_gemini
GEMINI_MODEL=gemini-3.5-flash-lite
GROQ_API_KEY=votre_cle_groq
GROQ_MODEL=openai/gpt-oss-120b
```

> **Ne commitez jamais ces clés.** Elles vont dans `.env` (ignoré par Git) ; seul `.env.example` est versionné. En production, elles sont des variables d'environnement de Render.

## 5. Installer et lancer le frontend

Dans un **second terminal**, depuis la racine du dépôt :

```bash
cd frontend
npm install
cp .env.example .env        # Windows PowerShell : Copy-Item .env.example .env
npm run dev
```

Le frontend tourne sur <http://localhost:5173>. Par défaut, `frontend/.env` contient :

```dotenv
VITE_API_URL=http://localhost:8000/api
```

Si vous changez le port du backend, mettez aussi à jour cette valeur puis relancez `npm run dev`.

## 6. Vérifier que tout fonctionne

1. Ouvrez <http://localhost:8000/api/health> : la réponse doit contenir `"status": "ok"` et `"db": "ok"`.
2. Ouvrez <http://localhost:5173> : l'application frontend doit s'afficher.
3. Ouvrez <http://localhost:8025> : l'interface Mailpit doit être accessible.

## 7. Comptes de démonstration

`php artisan migrate:fresh --seed` exécute `DemoSeeder` et crée trois comptes déjà activés. Ils utilisent tous le mot de passe **`Demo1234!`**.

| Profil | E-mail | Mot de passe |
|---|---|---|
| Étudiant | `etudiant@demo.uniconnect` | `Demo1234!` |
| Enseignant | `enseignant@demo.uniconnect` | `Demo1234!` |
| Administration | `admin@demo.uniconnect` | `Demo1234!` |

Le compte administration possède tous les droits : `GERER_PARAMETRAGE`, `GERER_UTILISATEURS`, `CREER_GROUPE_ORGA`, `CREER_GROUPE_PRIVE`, `CREER_CHANNEL`, `MODERER` et `CONSULTER_TABLEAU_BORD`.

Le seeder crée également 40 entrées dans `comptes_autorises` : 35 étudiants répartis dans les 3 classes (L2 Info A, L2 Info B, L3 Info), 4 enseignants et 1 membre de l'administration. Les 3 comptes ci-dessus sont activés ; les 37 autres restent à l'état `NON_CREE`.

## 8. Problèmes fréquents

| Problème | Solution |
|---|---|
| `docker compose` introuvable | Installez Docker Desktop et démarrez-le. |
| `could not find driver` ou erreur PostgreSQL | Activez l'extension `pdo_pgsql` dans `php.ini`, puis redémarrez le terminal. |
| `composer install` échoue sur `symfony/* requires php >=8.4.1` | Passez à PHP 8.4.1 ou plus. N'utilisez pas `--ignore-platform-req`. |
| `SQLSTATE[08006]` / connexion refusée | Attendez que PostgreSQL soit `healthy` (`docker compose ps`) puis relancez `php artisan migrate --seed`. |
| Authentification par mot de passe échouée pour « uniconnect » | Un PostgreSQL installé sur Windows (service `postgresql-x64-16`) occupe le port 5432. Le conteneur est publié sur `5433`. Si votre `backend/.env` date d'avant ce changement, mettez `DB_PORT=5433` ; pour revenir au 5432 : `"5432:5432"` dans `docker-compose.yml` et `DB_PORT=5432` dans `backend/.env`. Voir le commentaire de SCRUM-25. |
| Port `8000` ou `5173` déjà utilisé | Arrêtez le processus concerné, ou utilisez `php artisan serve --port=8001` et adaptez `VITE_API_URL`. |
| Les tests frontend échouent sur `localStorage` | Utilisez Node.js 22 ou 24 (Node 26 est concerné). |
| `npm` bloqué sous Windows (`npm.ps1 cannot be loaded`) | Utilisez `npm.cmd` à la place de `npm`, ou changez la politique d'exécution : `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass`. |
| L'Assistant IA répond **503** | Vérifiez `GEMINI_API_KEY` et `GROQ_API_KEY` dans `backend/.env`, puis `php artisan config:clear`. |
| L'Assistant IA répond **429** | Vous avez atteint vos 20 questions du jour : attendez minuit, ou utilisez un autre compte. |
| Les PDF ne sont pas indexés (`indexee_ia` reste `false`) | L'indexation passe par la file d'attente : lancez `php artisan queue:work` (sauf si `QUEUE_CONNECTION=sync`). Un PDF scanné (images, sans texte) ne peut pas être indexé : voir `storage/logs/laravel.log`. |
| `composer install` échoue sur `ext-gd` | Activez `extension=gd` dans `php.ini` (voir § 1). |

## 9. Commandes utiles

### Backend (depuis `backend/`)

| Commande | Rôle |
|---|---|
| `php artisan test` | Lance les tests PHPUnit (base SQLite en mémoire). |
| `vendor/bin/pint` | Formate le code PHP avec Pint. |
| `vendor/bin/pint --test` | Vérifie le formatage sans modifier les fichiers. |
| `php artisan migrate:fresh --seed` | Recrée la base et remplit les données de démonstration. |

### Frontend (depuis `frontend/`)

| Commande | Rôle |
|---|---|
| `npm run lint` | Lance oxlint puis ESLint. Corrige automatiquement. |
| `npm run test` | Lance les tests Vitest une seule fois. |
| `npm run check:lint` | Vérification sans modification (ce que lance la CI). |
| `npm run test:unit` | Vitest en mode watch. |
| `npm run build` | Construit l'application pour la production. |
| `npm run dev` | Lance le serveur de développement Vite. |

### Docker (depuis la racine)

| Commande | Rôle |
|---|---|
| `docker compose up -d` | Démarre PostgreSQL et Mailpit. |
| `docker compose ps` | Affiche l'état des services. |
| `docker compose logs -f postgres` | Affiche les logs PostgreSQL. |
| `docker compose down` | Arrête les services (les données restent dans le volume). |

## 10. Conventions Git

- Branche par ticket, créée depuis `main` : `feat/SCRUM-xx-description`.
- Commits : `SCRUM-xx: message` (message court et à l'impératif).
- Pas de push direct sur `main` : Pull Request obligatoire avec 1 relecture avant fusion.
- La CI GitHub Actions (`.github/workflows/`) lance sur chaque PR les jobs **backend** (Pint + tests sur PostgreSQL 16) et **frontend** (lint, build, Vitest). Les deux doivent être verts pour fusionner.
- Aucun secret dans Git : les clés vont dans `.env` (ignoré) ; seul `.env.example` est versionné.
- Ne jamais committer `vendor/`, `node_modules/`, `dist/` ni les fichiers `.env`.

## 11. Vérifier la santé de l'application

```bash
curl http://localhost:8000/api/health
# { "status": "ok", "version": "1.0.0", "db": "ok" }
```

Si `db` vaut `erreur`, vérifiez que PostgreSQL est `healthy` et que `DB_PORT=5433` dans `backend/.env`.
