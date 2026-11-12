# Collection Bruno — UniConnect

Collection [Bruno](https://www.usebruno.com/) des routes de l'API, utilisée pour
tester les endpoints manuellement. Elle couvre notamment les routes
`/api/admin/comptes-autorises` (SCRUM-75).

## Prérequis

1. Le backend tourne sur <http://localhost:8000> :

   ```sh
   cd backend
   php artisan migrate:fresh --seed
   php artisan serve
   ```

2. Le jeu de démonstration (`DemoSeeder`) crée le compte administrateur
   `admin@demo.uniconnect` / `Demo1234!` et trois comptes déjà activés. Sans lui,
   les requêtes de connexion et de conflit **409** échouent.

## Lancer la collection

### Avec l'application Bruno

1. Ouvrir Bruno, puis **Open Collection** et choisir ce dossier `bruno/`.
2. Sélectionner l'environnement **local**.
3. Lancer le dossier **00 - Authentification** en premier : la requête
   *Connexion administrateur* enregistre le jeton Sanctum dans la variable
   `token`, utilisée par toutes les autres requêtes.
4. Lancer le dossier **10 - Comptes autorisés** dans l'ordre (les numéros `01`
   à `08` donnent l'ordre d'exécution).

### En ligne de commande

```sh
cd bruno
npm install -g @usebruno/cli
bru run --env local
```

> Les filtres sont écrits directement dans l'URL (`…?profil=ETUDIANT&etat=NON_CREE&q=ben`)
> pour que le CLI les applique : les blocs `params:query` du fichier `.bru` sont
> ignorés par `bru run` 4.3.0. Le CLI doit être lancé **depuis le dossier
> `bruno/`**, sinon il répond « You can run only at the root of a collection ».

Les requêtes s'exécutent dans l'ordre : connexion, index, création, modification,
désactivation, doublon (422), suppression (204), puis suppression d'un compte
déjà créé (409).

## Variables

| Variable | Rôle |
|---|---|
| `baseUrl` | Racine de l'API (`http://localhost:8000/api`) |
| `token` | Jeton Sanctum, rempli automatiquement par la connexion |
| `compteId` | Autorisation créée par la requête `02`, réutilisée par `03` à `06` |
| `matriculeCree` | Matricule créé par `02`, réutilisé par le test de doublon |
| `compteLieId` | Premier compte actif du jeu de démonstration, pour le conflit **409** |

Pour tester avec un autre utilisateur, il suffit de modifier l'e-mail et le mot
de passe dans *Connexion administrateur* ou de renseigner `token` à la main dans
l'environnement **local**.
