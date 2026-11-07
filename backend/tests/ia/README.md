# Jeu de questions de l'Assistant IA (SCRUM-179)

`questions.json` contient 20 questions de référence utilisées par `php artisan ia:eval` (SCRUM-180).

- **Cours de démo :** `chap03.pdf` — *PL/SQL : Les curseurs* (5 pages).
- **`page` = numéro de page dans le PDF (1 à 5)**, c'est-à-dire la page renvoyée par l'extraction `smalot/pdfparser`, et non le numéro imprimé en bas de page (21 à 25).
- 16 questions dans le sujet (`hors_sujet: false`) et 4 hors sujet (`hors_sujet: true`, `document` et `page` à `null`) auxquelles l'assistant doit répondre qu'il ne trouve pas l'information.
- `reponse_attendue` sert à la relecture humaine ; l'évaluation automatique se base sur `document` et `page`.
