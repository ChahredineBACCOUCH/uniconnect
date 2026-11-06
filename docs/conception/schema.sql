-- UniConnect · schéma PostgreSQL 16 (référence pour les migrations Laravel)
-- Mis à jour le 06/11/2026 : IA gratuite (Gemini + Groq en secours) au lieu de Claude.
-- Conventions Laravel : id bigserial, created_at/updated_at timestamptz, clés étrangères <table_singulier>_id.
-- L'héritage UML est mappé en table unique : users.profil (Etudiant/Enseignant/MembreAdministration)
-- et espaces.type (GroupeOrganisationnel/GroupePrive/Channel).

CREATE TYPE profil          AS ENUM ('ETUDIANT','ENSEIGNANT','ADMINISTRATION');
CREATE TYPE etat_compte     AS ENUM ('NON_CREE','EN_ATTENTE','ACTIF','DESACTIVE','SUSPENDU');
CREATE TYPE droit           AS ENUM ('GERER_PARAMETRAGE','GERER_UTILISATEURS','CREER_GROUPE_ORGA','CREER_GROUPE_PRIVE','CREER_CHANNEL','MODERER','CONSULTER_TABLEAU_BORD');
CREATE TYPE type_espace     AS ENUM ('ORGANISATIONNEL','PRIVE','CHANNEL');
CREATE TYPE etat_espace     AS ENUM ('ACTIF','ARCHIVE');           -- SUPPRIME = suppression physique
CREATE TYPE mode_acces      AS ENUM ('OUVERT','SUR_DEMANDE','RESTREINT');
CREATE TYPE role_membre     AS ENUM ('PROPRIETAIRE','CO_GESTIONNAIRE','MEMBRE');
CREATE TYPE etat_invitation AS ENUM ('EN_ATTENTE','ACCEPTEE','REFUSEE','EXPIREE','ANNULEE');
CREATE TYPE motif_signal    AS ENUM ('INAPPROPRIE','HARCELEMENT','SPAM','ERRONE','AUTRE');
CREATE TYPE etat_signal     AS ENUM ('NOUVEAU','EN_COURS','TRAITE','REJETE');
CREATE TYPE type_decision   AS ENUM ('REJETER','MASQUER','SUPPRIMER','AVERTIR','RETIRER','SUSPENDRE');
-- Remarque Laravel : les migrations utilisent $table->enum() (CHECK) ou string + règle de validation ;
-- les types ENUM natifs ci-dessus documentent les valeurs autorisées.

-- ---------- Paramétrage académique ----------
CREATE TABLE annees_universitaires (
  id bigserial PRIMARY KEY,
  libelle varchar(9) NOT NULL UNIQUE,              -- 2026/2027
  date_debut date NOT NULL, date_fin date NOT NULL,
  active boolean NOT NULL DEFAULT false,
  created_at timestamptz, updated_at timestamptz,
  CHECK (date_fin > date_debut)
);
CREATE UNIQUE INDEX annee_une_seule_active ON annees_universitaires (active) WHERE active;   -- règle §3.1

CREATE TABLE departements (id bigserial PRIMARY KEY, code varchar(20) NOT NULL UNIQUE, libelle varchar(120) NOT NULL, actif boolean NOT NULL DEFAULT true, created_at timestamptz, updated_at timestamptz);
CREATE TABLE filieres (id bigserial PRIMARY KEY, code varchar(20) NOT NULL UNIQUE, libelle varchar(120) NOT NULL,
  departement_id bigint NOT NULL REFERENCES departements(id) ON DELETE RESTRICT, actif boolean NOT NULL DEFAULT true, created_at timestamptz, updated_at timestamptz);
CREATE TABLE niveaux (id bigserial PRIMARY KEY, code varchar(10) NOT NULL UNIQUE, libelle varchar(60) NOT NULL, created_at timestamptz, updated_at timestamptz);
CREATE TABLE classes (
  id bigserial PRIMARY KEY, code varchar(30) NOT NULL, libelle varchar(120) NOT NULL,
  filiere_id bigint NOT NULL REFERENCES filieres(id) ON DELETE RESTRICT,
  niveau_id bigint NOT NULL REFERENCES niveaux(id) ON DELETE RESTRICT,
  annee_id bigint NOT NULL REFERENCES annees_universitaires(id) ON DELETE RESTRICT,
  actif boolean NOT NULL DEFAULT true, created_at timestamptz, updated_at timestamptz,
  UNIQUE (code, annee_id)
);
CREATE TABLE matieres (id bigserial PRIMARY KEY, code varchar(30) NOT NULL UNIQUE, libelle varchar(160) NOT NULL, semestre smallint NOT NULL CHECK (semestre BETWEEN 1 AND 2), actif boolean NOT NULL DEFAULT true, created_at timestamptz, updated_at timestamptz);
CREATE TABLE classe_matiere (classe_id bigint REFERENCES classes(id) ON DELETE CASCADE, matiere_id bigint REFERENCES matieres(id) ON DELETE RESTRICT, PRIMARY KEY (classe_id, matiere_id));

CREATE TABLE regles_plateforme (            -- une seule ligne (singleton)
  id smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  durees_proposees jsonb NOT NULL,          -- [{"code":"24H","heures":24},{"code":"7J","heures":168},...,{"code":"ILLIMITEE","heures":null}]
  duree_defaut jsonb NOT NULL,              -- {"ORGANISATIONNEL":"30J","PRIVE":"7J","CHANNEL":"30J"}
  duree_max jsonb NOT NULL,
  taille_max_fichier_mo int NOT NULL DEFAULT 25,
  formats_autorises jsonb NOT NULL,
  delai_invitation_jours int NOT NULL DEFAULT 14,
  heure_traitement time NOT NULL DEFAULT '02:00',
  updated_at timestamptz
);

-- ---------- Utilisateurs et droits ----------
CREATE TABLE users (
  id bigserial PRIMARY KEY,
  matricule varchar(30) NOT NULL UNIQUE,
  nom varchar(80) NOT NULL, prenom varchar(80) NOT NULL,
  email varchar(190) NOT NULL UNIQUE,
  password varchar(255),
  photo varchar(255),
  profil profil NOT NULL,
  classe_id bigint REFERENCES classes(id) ON DELETE SET NULL,        -- étudiants
  etat etat_compte NOT NULL DEFAULT 'EN_ATTENTE',
  tentatives_echouees smallint NOT NULL DEFAULT 0,
  bloque_jusqua timestamptz, suspendu_jusqua timestamptz,
  derniere_connexion timestamptz, email_verified_at timestamptz,
  remember_token varchar(100), created_at timestamptz, updated_at timestamptz,
  CHECK (profil <> 'ETUDIANT' OR classe_id IS NOT NULL OR etat <> 'ACTIF')
);
CREATE TABLE comptes_autorises (
  id bigserial PRIMARY KEY,
  matricule varchar(30) NOT NULL UNIQUE,
  nom varchar(80) NOT NULL, prenom varchar(80) NOT NULL,
  email varchar(190) NOT NULL UNIQUE,
  profil profil NOT NULL,
  classe_id bigint REFERENCES classes(id) ON DELETE RESTRICT,
  etat etat_compte NOT NULL DEFAULT 'NON_CREE',
  user_id bigint UNIQUE REFERENCES users(id) ON DELETE SET NULL,
  created_at timestamptz, updated_at timestamptz
);
CREATE INDEX comptes_autorises_filtres ON comptes_autorises (profil, classe_id, etat);
CREATE TABLE droits_utilisateur (user_id bigint REFERENCES users(id) ON DELETE CASCADE, droit droit NOT NULL, accorde_le timestamptz NOT NULL DEFAULT now(), PRIMARY KEY (user_id, droit));
CREATE TABLE enseignant_matiere (user_id bigint REFERENCES users(id) ON DELETE CASCADE, matiere_id bigint REFERENCES matieres(id) ON DELETE CASCADE, classe_id bigint REFERENCES classes(id) ON DELETE CASCADE, PRIMARY KEY (user_id, matiere_id, classe_id));
CREATE TABLE preferences_notification (user_id bigint PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, par_email boolean NOT NULL DEFAULT true, types_actifs jsonb NOT NULL DEFAULT '[]');
-- + tables Laravel standard : personal_access_tokens (Sanctum), password_reset_tokens, notifications, jobs, failed_jobs, cache.

-- ---------- Espaces ----------
CREATE TABLE espaces (
  id bigserial PRIMARY KEY,
  type type_espace NOT NULL,
  nom varchar(120) NOT NULL, description text, image varchar(255),
  etat etat_espace NOT NULL DEFAULT 'ACTIF',
  duree_visibilite_heures int,              -- NULL = illimitée
  duree_courte_autorisee boolean NOT NULL DEFAULT false,
  mode_acces mode_acces, mode_annonce boolean NOT NULL DEFAULT false, audience jsonb,
  annee_id bigint REFERENCES annees_universitaires(id) ON DELETE RESTRICT,
  classe_id bigint REFERENCES classes(id) ON DELETE RESTRICT,
  matiere_id bigint REFERENCES matieres(id) ON DELETE RESTRICT,
  created_by bigint REFERENCES users(id) ON DELETE SET NULL,
  created_at timestamptz, updated_at timestamptz,
  CHECK (type <> 'CHANNEL' OR mode_acces IS NOT NULL),
  CHECK (type <> 'ORGANISATIONNEL' OR (annee_id IS NOT NULL AND classe_id IS NOT NULL))
);
CREATE INDEX espaces_type_etat ON espaces (type, etat);
CREATE UNIQUE INDEX espace_orga_unique ON espaces (annee_id, classe_id, COALESCE(matiere_id, 0)) WHERE type = 'ORGANISATIONNEL';  -- génération idempotente

CREATE TABLE adhesions (
  id bigserial PRIMARY KEY,
  espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE,
  user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role role_membre NOT NULL DEFAULT 'MEMBRE',
  notifications_actives boolean NOT NULL DEFAULT true,
  dernier_message_lu_id bigint,             -- compteur de non-lus
  created_at timestamptz, updated_at timestamptz,
  UNIQUE (espace_id, user_id)
);
CREATE UNIQUE INDEX un_proprietaire_par_espace ON adhesions (espace_id) WHERE role = 'PROPRIETAIRE';
CREATE INDEX adhesions_user ON adhesions (user_id);

CREATE TABLE invitations (
  id bigserial PRIMARY KEY,
  espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE,
  invite_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  invite_par_id bigint REFERENCES users(id) ON DELETE SET NULL,
  etat etat_invitation NOT NULL DEFAULT 'EN_ATTENTE',
  expire_le timestamptz NOT NULL,
  created_at timestamptz, updated_at timestamptz
);
CREATE UNIQUE INDEX invitation_en_attente_unique ON invitations (espace_id, invite_id) WHERE etat = 'EN_ATTENTE';
CREATE TABLE demandes_adhesion (
  id bigserial PRIMARY KEY,
  espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE,
  user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  etat etat_invitation NOT NULL DEFAULT 'EN_ATTENTE',
  created_at timestamptz, updated_at timestamptz
);

-- ---------- Messagerie et pédagogie ----------
CREATE TABLE messages (
  id bigserial PRIMARY KEY,
  espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE,
  auteur_id bigint REFERENCES users(id) ON DELETE SET NULL,
  parent_id bigint REFERENCES messages(id) ON DELETE SET NULL,
  contenu text NOT NULL,
  epingle boolean NOT NULL DEFAULT false,
  marque_utile boolean NOT NULL DEFAULT false,
  masque boolean NOT NULL DEFAULT false,
  modifie_le timestamptz,
  expire_le timestamptz,                    -- fixé à l'envoi : non rétroactif (§9)
  recherche tsvector GENERATED ALWAYS AS (to_tsvector('french', contenu)) STORED,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz
);
CREATE INDEX messages_fil ON messages (espace_id, id DESC);
CREATE INDEX messages_a_purger ON messages (expire_le) WHERE expire_le IS NOT NULL AND NOT epingle;
CREATE INDEX messages_recherche ON messages USING gin (recherche);

CREATE TABLE pieces_jointes (
  id bigserial PRIMARY KEY,
  message_id bigint NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
  nom varchar(255) NOT NULL, mime varchar(120) NOT NULL,
  taille_octets bigint NOT NULL CHECK (taille_octets > 0),
  chemin varchar(500) NOT NULL,             -- clé S3/R2, jamais exposée directement
  expire_le timestamptz, created_at timestamptz
);
CREATE TABLE annonces (
  id bigserial PRIMARY KEY,
  espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE,
  auteur_id bigint REFERENCES users(id) ON DELETE SET NULL,
  titre varchar(160) NOT NULL, contenu text NOT NULL,
  notifier_email boolean NOT NULL DEFAULT false,
  created_at timestamptz, updated_at timestamptz
);
CREATE TABLE chapitres (id bigserial PRIMARY KEY, espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE, titre varchar(160) NOT NULL, ordre smallint NOT NULL DEFAULT 0, created_at timestamptz, updated_at timestamptz);
CREATE TABLE ressources (
  id bigserial PRIMARY KEY,
  chapitre_id bigint NOT NULL REFERENCES chapitres(id) ON DELETE CASCADE,
  auteur_id bigint REFERENCES users(id) ON DELETE SET NULL,
  titre varchar(160) NOT NULL, description text,
  chemin_fichier varchar(500), url varchar(500), mime varchar(120),
  indexee_ia boolean NOT NULL DEFAULT false,
  created_at timestamptz, updated_at timestamptz,
  CHECK (chemin_fichier IS NOT NULL OR url IS NOT NULL)
);

-- ---------- Assistant IA ----------
CREATE TABLE assistants_ia (
  espace_id bigint PRIMARY KEY REFERENCES espaces(id) ON DELETE CASCADE,   -- 0..1 par espace
  actif boolean NOT NULL DEFAULT false,
  fournisseur varchar(20) NOT NULL DEFAULT 'gemini',   -- gemini (principal) | groq (secours) : offres gratuites
  modele varchar(60),                                 -- nom du modèle relevé dans AI Studio / console Groq (.env)
  instructions text, citer_sources boolean NOT NULL DEFAULT true,
  updated_at timestamptz
);
CREATE TABLE fragments_document (
  id bigserial PRIMARY KEY,
  ressource_id bigint NOT NULL REFERENCES ressources(id) ON DELETE CASCADE,
  page int, ordre int NOT NULL,
  contenu text NOT NULL,
  recherche tsvector GENERATED ALWAYS AS (to_tsvector('french', contenu)) STORED,
  created_at timestamptz
);
CREATE INDEX fragments_recherche ON fragments_document USING gin (recherche);
CREATE TABLE conversations_ia (id bigserial PRIMARY KEY, espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE, user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE, titre varchar(160), created_at timestamptz, updated_at timestamptz);
CREATE TABLE echanges_ia (
  id bigserial PRIMARY KEY,
  conversation_id bigint NOT NULL REFERENCES conversations_ia(id) ON DELETE CASCADE,
  question text NOT NULL, reponse text,
  avis_utile boolean,
  tokens_entree int, tokens_sortie int, duree_ms int,
  fournisseur varchar(20),                   -- gemini | groq : IA qui a réellement répondu (SCRUM-146)
  statut varchar(20) NOT NULL DEFAULT 'OK',  -- OK | SANS_SOURCE | ERREUR
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX echanges_quota ON echanges_ia (created_at);
CREATE TABLE sources_citees (id bigserial PRIMARY KEY, echange_id bigint NOT NULL REFERENCES echanges_ia(id) ON DELETE CASCADE, fragment_id bigint REFERENCES fragments_document(id) ON DELETE SET NULL, extrait text, score real);
CREATE TABLE resumes_ia (id bigserial PRIMARY KEY, espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE, user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE, periode_debut timestamptz NOT NULL, periode_fin timestamptz NOT NULL, contenu text NOT NULL, nb_messages int NOT NULL, created_at timestamptz);

-- ---------- Modération et traçabilité ----------
CREATE TABLE signalements (
  id bigserial PRIMARY KEY,
  message_id bigint REFERENCES messages(id) ON DELETE SET NULL,
  espace_id bigint NOT NULL REFERENCES espaces(id) ON DELETE CASCADE,
  signale_par_id bigint REFERENCES users(id) ON DELETE SET NULL,
  motif motif_signal NOT NULL, commentaire text,
  etat etat_signal NOT NULL DEFAULT 'NOUVEAU',
  created_at timestamptz, updated_at timestamptz,
  UNIQUE (message_id, signale_par_id)
);
CREATE INDEX signalements_file ON signalements (etat, created_at);
CREATE TABLE decisions_moderation (
  id bigserial PRIMARY KEY,
  signalement_id bigint NOT NULL UNIQUE REFERENCES signalements(id) ON DELETE CASCADE,
  moderateur_id bigint REFERENCES users(id) ON DELETE SET NULL,
  type type_decision NOT NULL,
  commentaire text NOT NULL CHECK (length(trim(commentaire)) > 0),
  created_at timestamptz
);
CREATE TABLE operations (            -- historique (§13) ; ne stocke jamais le contenu des messages
  id bigserial PRIMARY KEY,
  user_id bigint REFERENCES users(id) ON DELETE SET NULL,
  type varchar(60) NOT NULL, objet varchar(120) NOT NULL,
  ancienne_valeur jsonb, nouvelle_valeur jsonb,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX operations_filtres ON operations (created_at DESC, type);
CREATE TABLE traitements_expiration (id bigserial PRIMARY KEY, execute_le timestamptz NOT NULL, nb_messages int NOT NULL, nb_fichiers int NOT NULL, duree_ms int);
