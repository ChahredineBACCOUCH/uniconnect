# Image Docker de production — API UniConnect

Un seul conteneur fait tourner l'API Laravel (SCRUM-30) :

| Processus | Rôle | Géré par |
|---|---|---|
| `nginx` | Serveur web, expose le port **80** | Supervisor |
| `php-fpm` | Exécute PHP (écoute `127.0.0.1:9000`) | Supervisor |
| `queue-worker` | `php artisan queue:work --tries=3` | Supervisor |
| `scheduler` | `php artisan schedule:work` | Supervisor |

Au démarrage, `start.sh` attend que la base réponde, applique `php artisan migrate --force`,
met la configuration en cache (`php artisan config:cache`) puis lance `supervisord`.

## Test en local

```bash
# 1. Construire l'image (contexte = backend/)
docker build -t uniconnect-api backend

# 2. Lancer avec les variables d'environnement de backend/.env
docker run -p 8080:80 --env-file backend/.env uniconnect-api

# 3. Vérifier
curl http://localhost:8080/api/health
# {"status":"ok","version":"1.0.0","db":"ok"}
```

> `backend/.env` n'est pas versionné. En local, `DB_HOST=127.0.0.1` ne fonctionne pas
> depuis le conteneur : utilisez `host.docker.internal` (Docker Desktop) ou le nom du
> service PostgreSQL si les deux conteneurs partagent un réseau Docker.

## Déploiement sur Render

| Réglage Render | Valeur |
|---|---|
| Language / Runtime | **Docker** |
| Root Directory | `backend` |
| Dockerfile Path | `Dockerfile` (ou `backend/Dockerfile`) |
| Health Check Path | `/api/health` |
| `PORT` (variable) | `80` — nginx écoute sur le port 80 |

Variables d'environnement : voir [`docs/deploiement.md`](../../docs/deploiement.md#render-backend).
Au minimum : `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*`
(ou `DB_URL` pour Neon), `SESSION_DRIVER=database`, `CACHE_STORE=database`,
`QUEUE_CONNECTION=database`.

Remarques :

- Les migrations sont appliquées **à chaque démarrage** du conteneur : elles doivent rester idempotentes.
- Les logs nginx/php-fpm/Supervisor sont écrits sur `stdout`/`stderr`, donc visibles dans **Render → Logs**.
- Le système de fichiers Render est éphémère : ne stockez pas de pièces jointes sur le disque local
  (utilisez le stockage S3 prévu dans `docs/deploiement.md`).
- L'image utilise **PHP 8.4** (et non 8.3) car `backend/composer.lock` verrouille Symfony 8.1,
  qui exige PHP >= 8.4.1.
