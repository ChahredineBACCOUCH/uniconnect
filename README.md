# UniConnect

Application de communication universitaire : espaces de classe et de matière, groupes privés, channels, et un Assistant IA qui répond à partir des supports de cours.

Projet étudiant 2026/2027 · livraison le 26/11/2026.

## Structure du dépôt

| Dossier | Contenu |
|---|---|
| `backend/` | API Laravel + Sanctum, PostgreSQL |
| `frontend/` | Application Vue 3 + Vuetify + Pinia |

Le guide d'installation complet arrive avec le ticket SCRUM-26.

## Conventions

- Branche par ticket : `feat/SCRUM-xx-description`
- Commits : `SCRUM-xx: message`
- Pas de push direct sur `main` : Pull Request + 1 relecture obligatoire.
- Aucun secret dans Git : les clés vont dans `.env` (ignoré) ; seul `.env.example` est versionné.

Suivi : Jira [uniconnectteam26](https://uniconnectteam26.atlassian.net/jira/software/projects/SCRUM/boards/1)
