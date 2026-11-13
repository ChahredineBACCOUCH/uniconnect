# Évaluation de l'Assistant IA

Résultats de `php artisan ia:eval` (SCRUM-180, US-43) sur le jeu de questions de référence `backend/tests/ia/questions.json` (SCRUM-179).

- **Date :** 13/11/2026 à 14:17
- **Cours :** `chap03.pdf`, découpé en 6 fragment(s)
- **Chaîne testée :** celle de l'application — `IndexationService` (fragments d'environ 1 500 caractères) puis `AssistantService` (6 meilleurs extraits en recherche plein texte, Gemini avec secours Groq, citations `[n]`). L'espace de test est créé dans une transaction annulée à la fin.

## Bilan

| Indicateur | Résultat |
|---|---|
| **Sources correctes** (questions du cours) | **16 / 16** (100 %) |
| **Refus corrects** (questions hors sujet) | **4 / 4** |
| Erreurs (Gemini et Groq indisponibles) | 0 |
| Questions sans appel à l'IA (aucun extrait pertinent) | 1 |
| Fournisseurs | gemini × 19 |
| Durée de réponse | 8,3 s en moyenne, 20,0 s au maximum |
| Tokens | 32449 en entrée, 923 en sortie |
| **Coût total** | **0 €** (offres gratuites de Gemini et de Groq) |

## Méthode

- **Question du cours :** correcte si la page attendue fait partie des pages **citées par le modèle** dans sa réponse (citations vérifiées par le backend).
- **Question hors sujet :** correcte si l'assistant refuse (`SANS_SOURCE`), soit parce qu'aucun extrait n'est trouvé (l'IA n'est pas appelée), soit parce que le modèle répond `JE_NE_SAIS_PAS`.
- **Limite :** la fidélité de chaque phrase à sa source n'est pas vérifiée automatiquement ; le champ `reponse_attendue` du jeu de questions sert à la relecture humaine. Les résultats d'un modèle d'IA peuvent varier d'une exécution à l'autre.

## Détail

| N° | Question | Attendu | Statut | Pages citées | Correct | Fournisseur | Durée (s) | Tokens |
|---|---|---|---|---|---|---|---|---|
| 1 | Qu'est-ce qu'un curseur PL/SQL ? | p. 1 | `OK` | 1 | ✅ | gemini · gemini-3.5-flash-lite | 20,0 | 2215 |
| 2 | Quelle est la différence entre un curseur explicite et un curseur implicite ? | p. 1 | `OK` | 1 | ✅ | gemini · gemini-3.5-flash-lite | 18,2 | 2207 |
| 3 | Quelles sont les étapes pour utiliser un curseur explicite ? | p. 1 | `OK` | 1, 2 | ✅ | gemini · gemini-3.5-flash-lite | 9,8 | 2214 |
| 4 | Dans quelle partie d'un bloc PL/SQL doit-on déclarer un curseur ? | p. 2 | `OK` | 2 | ✅ | gemini · gemini-3.5-flash-lite | 5,3 | 2163 |
| 5 | Que se passe-t-il au moment où on ouvre un curseur avec OPEN ? | p. 2 | `OK` | 2 | ✅ | gemini · gemini-3.5-flash-lite | 14,4 | 2211 |
| 6 | Pourquoi faut-il fermer un curseur ? | p. 2 | `OK` | 2 | ✅ | gemini · gemini-3.5-flash-lite | 8,5 | 2161 |
| 7 | Combien de lignes l'instruction FETCH récupère-t-elle à chaque appel ? | p. 2 | `OK` | 2 | ✅ | gemini · gemini-3.5-flash-lite | 8,4 | 2159 |
| 8 | Quels sont les attributs d'un curseur explicite ? | p. 3 | `OK` | 3 | ✅ | gemini · gemini-3.5-flash-lite | 4,9 | 2193 |
| 9 | Que contient l'attribut %ROWCOUNT ? | p. 3 | `OK` | 3 | ✅ | gemini · gemini-3.5-flash-lite | 3,5 | 905 |
| 10 | Comment teste-t-on le résultat d'un curseur implicite après un UPDATE ? | p. 3 | `OK` | 3 | ✅ | gemini · gemini-3.5-flash-lite | 4,0 | 2308 |
| 11 | Quand l'attribut %ISOPEN vaut-il VRAI ? | p. 3 | `OK` | 3 | ✅ | gemini · gemini-3.5-flash-lite | 5,3 | 539 |
| 12 | Comment parcourir toutes les lignes d'un curseur sans écrire soi-même OPEN, FETCH et CLOSE ? | p. 4 | `OK` | 4 | ✅ | gemini · gemini-3.5-flash-lite | 15,8 | 2269 |
| 13 | À quoi sert un curseur paramétré ? | p. 4 | `OK` | 4 | ✅ | gemini · gemini-3.5-flash-lite | 3,6 | 2159 |
| 14 | Quels types peut-on donner aux paramètres d'un curseur ? | p. 4 | `OK` | 4 | ✅ | gemini · gemini-3.5-flash-lite | 4,8 | 2167 |
| 15 | À quoi sert la clause WHERE CURRENT OF ? | p. 5 | `OK` | 5 | ✅ | gemini · gemini-3.5-flash-lite | 15,2 | 1286 |
| 16 | Que faut-il ajouter à la déclaration du curseur pour pouvoir utiliser WHERE CURRENT OF ? | p. 5 | `OK` | 5 | ✅ | gemini · gemini-3.5-flash-lite | 4,7 | 2190 |
| 17 | Qui a gagné la Coupe du monde de football 2022 ? | refus | `SANS_SOURCE` | - | ✅ | gemini · gemini-3.5-flash-lite | 3,9 | 526 |
| 18 | Quel temps fera-t-il demain à Tunis ? | refus | `SANS_SOURCE` | - | ✅ | gemini · gemini-3.5-flash-lite | 3,3 | 518 |
| 19 | Qu'est-ce que la troisième forme normale (3FN) ? | refus | `SANS_SOURCE` | - | ✅ | - (pas d'appel) | 0,0 | - |
| 20 | Comment créer un composant Vue 3 avec Vuetify ? | refus | `SANS_SOURCE` | - | ✅ | gemini · gemini-3.5-flash-lite | 3,9 | 982 |

## Relancer l'évaluation

```bash
cd backend
php artisan ia:eval chemin/vers/chap03.pdf
```

Les clés `GEMINI_API_KEY` et `GROQ_API_KEY` doivent être renseignées dans `backend/.env`. Une pause de 4 s entre deux questions respecte la limite gratuite de Gemini (15 requêtes par minute).
