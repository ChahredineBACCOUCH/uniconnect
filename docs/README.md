# Documentation UniConnect

Toute la documentation du projet, à lire **avant de commencer un ticket**.
Livraison : **jeudi 19/11/2026** · gel du code : **mardi 17/11 à 18 h**.

| Document | À quoi il sert | Ticket Jira |
|---|---|---|
| [installation.md](installation.md) | Installer le projet de zéro (prérequis, Docker, backend, frontend, clés Gemini/Groq, dépannage) | SCRUM-186, SCRUM-26 |
| [architecture.md](architecture.md) | Comment l'application est construite : schéma, dossiers, sécurité, fonctionnement de l'Assistant IA | SCRUM-186 |
| [api.md](api.md) | **Contrat de l'API** : routes, données envoyées et reçues, codes d'erreur. Le backend et le frontend s'appuient dessus | SCRUM-186 |
| [ia-evaluation.md](ia-evaluation.md) | Résultats de l'évaluation de l'Assistant IA (`php artisan ia:eval`) | SCRUM-180 |
| [conception/](conception/) | Schéma SQL, diagramme de classes, diagramme de cas d'utilisation | US-05, SCRUM-47 |
| [maquette/](maquette/) | Maquette des écrans, à donner à votre IA de développement | — |
| [deploiement.md](deploiement.md) | Mettre en ligne (Neon, Render, Vercel), variables d'environnement, retour arrière | SCRUM-34 |
| [plan-de-tests.md](plan-de-tests.md) | Parcours à tester par profil et règles à vérifier avant la livraison | SCRUM-174 |
| [tests.md](tests.md) | **Plan de tests de la recette finale** : tableau parcours × profils, règles bloquantes et test automatique qui les couvre, procédure de recette croisée | SCRUM-174 |
| [api/](api/) | Collection Bruno versionnée des routes de l'API (Auth, Espaces, Messages, Fichiers, IA, Admin) | SCRUM-175 |
| [demo.md](demo.md) | Scénario de la démonstration du 19/11 (15 minutes) | SCRUM-187 |
| [guide-utilisateur.md](guide-utilisateur.md) | Mode d'emploi pour l'étudiant, l'enseignant et l'administration | SCRUM-185 |

Installation du projet en local : voir le [README principal](../README.md).

## Règles de mise à jour

- Si votre ticket **change** une route, une table ou un écran, mettez à jour le document correspondant **dans la même Pull Request**.
- `api.md` et `conception/schema.sql` sont la référence : en cas de désaccord entre deux membres, on suit ces fichiers, ou on les corrige ensemble par une PR.
- Les fonctionnalités marquées **v1.1** ne sont pas livrées le 19/11 : ne les codez pas avant.
