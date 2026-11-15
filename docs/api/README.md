# Collection Bruno — UniConnect (docs/api)

Collection [Bruno](https://www.usebruno.com/) des routes de l'API UniConnect,
rangee par dossier : **Auth, Espaces, Messages, Fichiers, IA, Admin**.

## Prerequis

1. Le backend tourne sur `http://localhost:8000` :

   ```sh
   cd backend
   php artisan migrate:fresh --seed
   php artisan serve
   ```

2. Le jeu de demonstration (`DemoSeeder`) cree les comptes de demo
   (`admin@demo.uniconnect`, `enseignant@demo.uniconnect`, `etudiant@demo.uniconnect`,
   mot de passe `Demo1234!`).

## Lancer la collection

1. Ouvrir Bruno, puis **Open Collection** et choisir ce dossier `docs/api/`.
2. Selectionner l'environnement **local**.
3. Lancer le dossier **Auth** en premier : la requete *Connexion administrateur*
   enregistre le jeton Sanctum dans la variable `token`, utilisee par toutes les
   autres requetes.
4. Lancer les autres dossiers dans l'ordre souhaite.

## Variables

| Variable | Role |
|---|---|
| `baseUrl` | Racine de l'API (`http://localhost:8000/api`) |
| `token` | Jeton Sanctum, rempli automatiquement par la connexion |
| `espaceId`, `invitationId`, `annonceId`, `ressourceId`, `chapitreId`, `messageId`, `notificationId` | Identifiants reutilises d'une requete a l'autre |

Pour tester avec un autre utilisateur, modifier l'e-mail et le mot de passe dans
*Connexion administrateur* ou renseigner `token` a la main dans l'environnement
**local**.

Les routes reportees en v1.1 (channels, signalements, modération, demandes
d'adhesion, purge) ne sont pas couvertes : voir `docs/api.md` section
« Routes reportees en v1.1 ».
