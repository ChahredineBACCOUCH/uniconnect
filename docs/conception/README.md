# Conception

| Fichier | Contenu |
|---|---|
| `schema.sql` | Schéma PostgreSQL 16 de référence (32 tables). **Les migrations Laravel doivent reproduire exactement ces colonnes et contraintes.** |
| `diagramme-classes.png` / `.svg` / `.pdf` | Diagramme de classes (modèle du domaine), à mettre dans la présentation |
| `diagramme-classes.puml` | Source PlantUML du diagramme de classes |
| `cas-utilisation.png` / `.svg` / `.pdf` | Diagramme de cas d'utilisation : les cas en pointillés gris sont prévus en v1.1 |
| `cas-utilisation.puml` | Source PlantUML du diagramme de cas d'utilisation |

## Utiliser `schema.sql` pour écrire une migration

1. Cherchez la table dans `schema.sql` (par exemple `CREATE TABLE classes`).
2. Créez la migration : `php artisan make:migration create_classes_table`.
3. Recopiez chaque colonne avec le bon type Laravel :

| SQL | Laravel |
|---|---|
| `bigserial PRIMARY KEY` | `$table->id()` |
| `bigint NOT NULL REFERENCES filieres(id) ON DELETE RESTRICT` | `$table->foreignId('filiere_id')->constrained()->restrictOnDelete()` |
| `... ON DELETE CASCADE` | `->cascadeOnDelete()` |
| `... ON DELETE SET NULL` | `->nullable()->constrained()->nullOnDelete()` |
| `varchar(120) NOT NULL` | `$table->string('libelle', 120)` |
| `boolean NOT NULL DEFAULT true` | `$table->boolean('actif')->default(true)` |
| `timestamptz` | `$table->timestampTz('...')` ; `created_at`/`updated_at` → `$table->timestampsTz()` |
| `jsonb` | `$table->jsonb('...')` |
| type `ENUM` (ex. `profil`) | `$table->enum('profil', ['ETUDIANT', 'ENSEIGNANT', 'ADMINISTRATION'])` |
| `UNIQUE (code, annee_id)` | `$table->unique(['code', 'annee_id'])` |
| index partiel `... WHERE active` | `DB::statement('CREATE UNIQUE INDEX ... WHERE active')` après la création de la table |
| colonne `tsvector GENERATED ALWAYS AS (...)` | `DB::statement('ALTER TABLE messages ADD COLUMN recherche tsvector GENERATED ALWAYS AS (to_tsvector(\'french\', contenu)) STORED')` |

4. Respectez l'ordre de création à cause des clés étrangères : années → départements → filières → niveaux → classes → matières → … (l'ordre du fichier).
5. Vérifiez avec `php artisan migrate:fresh` (tout recréer) : aucune erreur.

## Modifier un diagramme

- **Diagrammes PlantUML** (`.puml`) : installez l'extension « PlantUML » de VS Code et ouvrez l'aperçu (Alt+D).
- Si vous changez une table, mettez à jour **à la fois** `schema.sql` et le diagramme de classes dans la même PR.

## Mise à jour du 06/11/2026

L'Assistant IA utilise désormais des offres **gratuites** : Gemini, avec Groq en secours. Trois éléments ont changé en conséquence :
- `assistants_ia.modele` (valeur par défaut `claude-opus-5-5`) est remplacé par `fournisseur` (valeur par défaut `gemini`) et par `modele`, qui peut être vide ;
- `echanges_ia` reçoit une colonne `fournisseur`, qui indique quelle IA a réellement répondu (SCRUM-146) ;
- le diagramme de classes est mis à jour en conséquence.
