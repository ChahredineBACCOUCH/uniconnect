# Plan de tests (version du 19/11)

Deux niveaux de tests :
1. **Tests automatiques**, écrits avec chaque ticket et lancés par la CI : `php artisan test`, `npm run test`.
2. **Recette manuelle** le **18/11** en production : chaque membre teste les parcours qu'il **n'a pas développés** (SCRUM-176).

## 1. Comptes de test

Créés par le seeder de démonstration (SCRUM-42 et SCRUM-188).

| Profil | Compte | Rattachement |
|---|---|---|
| Étudiant | `yassine.bensalah@univ.tn` | L2 Informatique A |
| Étudiant (2ᵉ, pour les tests de droits) | `nour.kallel@univ.tn` | L2 Informatique A |
| Étudiant d'une autre classe | `aya.benamor@univ.tn` | L2 Informatique B |
| Enseignant | `sarra.benali@univ.tn` | Bases de données · L2 A |
| Administration | `leila.haddad@univ.tn` | Tous les droits |
| Personne autorisée, compte pas encore créé | `rania.gharbi@univ.tn` | pour tester l'inscription |

🔐 Le mot de passe des comptes de démo est lu dans la variable `DEMO_PASSWORD` (`.env` / Render). Il est **partagé en privé dans l'équipe**, jamais écrit dans le dépôt, qui est public.

## 2. Règles bloquantes à couvrir par des tests automatiques (Feature)

| # | Règle | Résultat attendu | Ticket |
|---|---|---|---|
| R1 | Inscription avec un e-mail absent de la liste des comptes autorisés | 422, aucun compte créé | SCRUM-65 |
| R2 | Inscription d'une personne dont le compte existe déjà | 409 | SCRUM-65 |
| R3 | Inscription sans année active | 422 | SCRUM-65 |
| R4 | Lien d'activation expiré | 410 | SCRUM-66 |
| R5 | 5 mauvais mots de passe de suite | 423 pendant 15 min dès le 5ᵉ essai | SCRUM-69 |
| R6 | Compte suspendu | Connexion refusée (403) | SCRUM-69 |
| R7 | Deux années actives en même temps | Impossible : l'activation de l'une désactive l'autre | SCRUM-74 |
| R8 | Import CSV avec doublon, e-mail invalide, classe inconnue | Lignes rejetées avec motif, aucune donnée importée lors de l'aperçu | SCRUM-78 |
| R9 | Génération des groupes lancée deux fois | Aucun doublon | SCRUM-84 |
| R10 | Génération des groupes sans année active | 422 | SCRUM-84 |
| R11 | Un non-membre lit les messages ou télécharge un fichier d'un espace | 403, même avec le lien direct | SCRUM-92, 96 |
| R12 | Un membre retiré d'un espace | 403 immédiatement sur messages et fichiers | SCRUM-92 |
| R13 | Un étudiant appelle une route `/admin/...` | 403 | SCRUM-175 |
| R14 | Question à l'IA sans fragment pertinent | `SANS_SOURCE`, IA **non** appelée | SCRUM-102 |
| R15 | Gemini répond 429 | Groq est appelé | SCRUM-102 |
| R16 | Gemini et Groq en erreur | 503 | SCRUM-102 |
| R17 | 21ᵉ question de la journée | 429 | SCRUM-145 |
| R18 | Assistant désactivé dans l'espace | 404 | SCRUM-145 |
| R19 | Fichier trop gros ou d'un format interdit | 422 | SCRUM-136 |

Frontend (Vitest) : store `auth` et client `http` ✅ (SCRUM-38), gardes du router (SCRUM-37).

## 3. Recette manuelle par profil (18/11, en production)

Cochez chaque ligne dans le commentaire de SCRUM-176. Toute anomalie devient un ticket **Bug** lié à la story, avec : étapes pour reproduire, résultat attendu, résultat obtenu, capture d'écran. Priorité *Highest* si elle bloque la démo.

### Visiteur
- [ ] P1 · Inscription de `rania.gharbi@univ.tn` → e-mail reçu → lien → mot de passe → arrivée sur l'accueil
- [ ] P2 · Inscription avec une adresse Gmail → message d'erreur sous le champ e-mail
- [ ] P3 · 5 mauvais mots de passe → message de blocage

### Étudiant (Yassine)
- [ ] E1 · Connexion → l'accueil affiche sa classe et ses matières, avec les compteurs de non-lus
- [ ] E2 · Discussion « Bases de données » : envoyer un message, puis le voir apparaître chez Nour (autre navigateur) en moins de 10 s
- [ ] E3 · Joindre un PDF à un message, puis le télécharger depuis l'autre compte
- [ ] E4 · Créer un groupe privé, inviter Nour ; Nour accepte depuis ses notifications
- [ ] E5 · Assistant IA : question du cours → réponse avec sources cliquables qui ouvrent le bon PDF
- [ ] E6 · Assistant IA : question hors sujet → « pas de réponse fiable dans le cours »
- [ ] E7 · 👍 / 👎 sur une réponse → le choix reste affiché
- [ ] E8 · Ouvrir le lien d'un espace dont il n'est pas membre (groupe d'Aya) → page 403
- [ ] E9 · Tout refaire sur **téléphone** (390 px) : navigation basse, aucun texte coupé

### Enseignant (Mme Ben Ali)
- [ ] T1 · Publier une annonce → les étudiants reçoivent une notification
- [ ] T2 · Créer un chapitre, ajouter un PDF → puce « Indexée pour l'IA » après quelques secondes
- [ ] T3 · Activer ou désactiver l'assistant → l'onglet apparaît ou disparaît chez les étudiants
- [ ] T4 · Nommer un co-gestionnaire, retirer un membre → le membre retiré perd l'accès

### Administration (Leila)
- [ ] A1 · Créer une année, puis l'activer → l'ancienne n'est plus active
- [ ] A2 · Référentiels : ajouter une classe ; supprimer une filière utilisée → refusé
- [ ] A3 · Import CSV : l'aperçu montre les erreurs ligne par ligne, puis l'import valide
- [ ] A4 · Générer les groupes → aperçu, puis création ; relancer → aucun doublon
- [ ] A5 · Un étudiant n'a accès à aucun écran Admin

### Transverse
- [ ] X1 · Chaque écran a un état « chargement » et un état « vide » (aucun écran blanc)
- [ ] X2 · Les erreurs 403, 404, 429 et 503 affichent un message en français
- [ ] X3 · `/api/health` → `db: ok`
- [ ] X4 · Aucune clé ni mot de passe visible dans le code du navigateur (onglet Sources des outils de développement)

## 4. Critère de livraison

Le 18/11 au soir, le filtre Jira `project = SCRUM AND type = Bug AND priority = Highest AND status != Terminé` doit être **vide**.
