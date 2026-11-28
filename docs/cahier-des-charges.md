# Application de communication universitaire : groupes, partage pédagogique et Assistant IA — version 1.0 déployée

## 1. Contexte

Au sein d'une université, les étudiants, les enseignants et les membres de l'administration doivent échanger des informations, partager des documents et organiser des discussions autour des cours, des classes et des activités de l'établissement. Aujourd'hui, ces échanges sont dispersés : groupes de messagerie personnels non maîtrisés par l'établissement, courriers électroniques, affichage physique, plateformes pédagogiques multiples. Il en résulte une perte d'information, l'absence de contrôle sur la diffusion des contenus et un suivi difficile des échanges.

**UniConnect** est l'application de communication universitaire réalisée pour répondre à ce besoin. Ce document décrit la **version 1.0, déployée en production** : ce qui est livré, les règles appliquées et ce qui est reporté à la version 1.1. La version 1.0 propose deux types d'espaces de communication :

- les **groupes organisationnels**, liés à la structure de l'université (année, classe, matière, enseignant), créés automatiquement ;
- les **groupes privés**, créés par un utilisateur autorisé et accessibles uniquement sur invitation.

Les **channels communautaires** existent dans le modèle de données ; leur création et leur annuaire sont prévus en version 1.1.

La version 1.0 ajoute un **Assistant IA** dans les groupes de matière : il répond aux questions des étudiants **uniquement à partir des supports publiés par l'enseignant**, en citant le document et la page.

Ce projet s'inscrit dans la matière « Projet d'intégration » et a été conduit selon une démarche Scrum (§16).

**Application en ligne** : <https://uniconnect-six-lake.vercel.app> · **Vidéo de démonstration** : <https://chahredinebaccouch.github.io/uniconnect/> · **Code source** : <https://github.com/ChahredineBACCOUCH/uniconnect>

## 2. Objectifs du projet

La version 1.0 permet :

- la gestion des utilisateurs selon trois profils (étudiant, enseignant, administration), avec des droits adaptés à chaque profil ;
- la création automatique des groupes par classe et par matière à partir des référentiels de la scolarité ;
- la création de groupes privés sur invitation ;
- l'envoi de messages contenant du texte, des images et des documents ;
- la définition, par le propriétaire de l'espace, d'une durée de visibilité des messages limitée ou illimitée ;
- la communication pédagogique autour des cours : annonces, ressources classées par chapitre ;
- un **Assistant IA** qui répond à partir des supports du cours, avec citation des sources ;
- le contrôle strict de l'accès aux conversations et aux documents, vérifié côté serveur ;
- une utilisation sur ordinateur et sur téléphone.

## 3. Paramétrage administratif préalable

Le paramétrage est réalisé par les membres de l'administration disposant du droit **Gérer le paramétrage**, dans la section « Administration » du menu.

### 3.1 Gestion des années universitaires

Un écran permet d'ajouter une année universitaire (ex. 2026/2027), de la modifier, de consulter la liste des années et d'activer une année. **Une seule année peut être active à la fois** : la règle est garantie par la base de données elle-même (index unique). À l'activation d'une nouvelle année, l'administration peut archiver les groupes de l'année précédente.

**Règle bloquante :** si aucune année universitaire n'est active, la plateforme est fermée : l'inscription est refusée (« plateforme fermée ») et la génération des groupes organisationnels est impossible, avec un message explicite.

### 3.2 Gestion des référentiels académiques

Des écrans dédiés permettent de saisir, modifier et désactiver :

- les départements ;
- les filières (rattachées à un département) ;
- les niveaux ;
- les classes (rattachées à un niveau, à une filière et à l'année) ;
- les matières, avec l'enseignant responsable et les classes concernées.

Un élément déjà utilisé ne peut pas être supprimé, seulement désactivé. Ces référentiels servent de base à la génération des groupes organisationnels (§6.1).

### 3.3 Gestion des comptes autorisés

Un utilisateur disposant du droit **Gérer les utilisateurs** constitue la liste des personnes autorisées à créer un compte : matricule, nom, prénom, adresse électronique institutionnelle, profil et, pour un étudiant, sa classe.

Fonctions livrées : liste paginée, recherche, filtres (profil, classe, état), ajout, modification, désactivation, suppression et **import Excel**. L'import se fait en deux temps : un **aperçu** vérifie chaque ligne sans rien enregistrer (lignes valides, doublons, erreurs expliquées ligne par ligne), puis seules les lignes valides sont importées. Un modèle Excel est téléchargeable.

**Règle bloquante :** une personne ne peut créer un compte que si son matricule et son adresse électronique institutionnelle figurent ensemble dans la liste des comptes autorisés de l'année active. Dans le cas contraire, la création est refusée avec un message explicite.

### 3.4 Gestion des droits utilisateurs

Chaque profil reçoit des droits par défaut à l'activation de son compte (par exemple, **Créer des groupes privés** pour les étudiants et les enseignants). Le compte d'administration initial dispose de tous les droits : Gérer le paramétrage, Gérer les utilisateurs, Créer des groupes organisationnels, Créer des groupes privés, Créer des channels, Modérer, Consulter le tableau de bord.

Les droits sont **vérifiés côté serveur à chaque requête**. L'écran d'attribution individuelle des droits est prévu en version 1.1.

### 3.5 Règles de visibilité par défaut

Les durées proposées sont : **24 heures, 7 jours, 30 jours ou illimitée**. Une durée par défaut s'applique à chaque type d'espace : 30 jours pour les groupes organisationnels, 7 jours pour les groupes privés. En version 1.0, ces valeurs sont fixées dans la configuration de l'application ; leur écran d'administration est prévu en version 1.1.

## 4. Profils des utilisateurs

### Étudiant

L'étudiant peut :

- créer son compte et l'activer par le lien reçu par e-mail ;
- accéder automatiquement aux groupes organisationnels de sa classe et de ses matières ;
- accepter ou refuser une invitation à un groupe privé ;
- créer un groupe privé (droit accordé par défaut) et inviter des membres ;
- envoyer des messages, des images et des documents dans les espaces dont il est membre ;
- consulter les annonces et télécharger les ressources partagées ;
- poser des questions à l'Assistant IA du cours (20 questions par jour) et noter les réponses ;
- consulter ses notifications.

### Enseignant

En plus des fonctions de l'étudiant, l'enseignant peut :

- être propriétaire des groupes des matières dont il est responsable ;
- publier des annonces, avec pièces jointes et envoi optionnel par e-mail ;
- classer ses supports de cours par chapitre (fichiers ou liens), les renommer, les déplacer, les supprimer ;
- activer l'Assistant IA de son cours et lui donner une consigne ;
- inviter, retirer des membres, nommer des co-gestionnaires et transférer la propriété dans les espaces qu'il gère.

### Membre de l'administration

Les actions du membre de l'administration découlent des droits qui lui sont accordés (§3.4) :

- paramétrer la plateforme (années universitaires, référentiels) ;
- gérer les comptes autorisés, y compris par import Excel ;
- générer, synchroniser et archiver les groupes organisationnels.

### Page profil (tout utilisateur)

La page de profil (photo, informations de contact, mot de passe, préférences de notification) est prévue en version 1.1. En version 1.0, un utilisateur ne peut modifier ni son profil ni ses droits.

### Matrice des permissions (version 1.0)

| Action | Étudiant | Enseignant | Administration |
|---|---|---|---|
| Générer les groupes organisationnels | Non | Non | Oui |
| Créer un groupe privé | Oui | Oui | Oui |
| Inviter / retirer des membres | Gestionnaire | Gestionnaire | Gestionnaire |
| Fixer la durée de visibilité (à la création) | Créateur | Créateur | Créateur |
| Publier une annonce | Gestionnaire | Gestionnaire | Gestionnaire |
| Déposer des ressources de cours | Gestionnaire | Gestionnaire | Gestionnaire |
| Activer l'Assistant IA | Non | Gestionnaire | Non |
| Modifier / supprimer un message | Ses messages | Ses messages | Ses messages |
| Gérer les comptes autorisés et les référentiels | Non | Non | Oui (selon droits) |

« Gestionnaire » désigne le propriétaire ou un co-gestionnaire de l'espace.

## 5. Processus d'accès à la plateforme

### Étape 0 : Prérequis

- Une année universitaire doit être active (§3.1).
- La personne doit figurer dans la liste des comptes autorisés (§3.3).

### Étape 1 : Création du compte

L'utilisateur renseigne son matricule, son nom, son prénom et son adresse électronique institutionnelle, puis accepte la charte. Le système effectue deux contrôles :

- **Éligibilité** : le matricule et l'adresse électronique doivent correspondre à une entrée de la liste des comptes autorisés ;
- **Unicité** : aucun compte ne doit déjà exister pour ce matricule.

Si les contrôles sont satisfaits, le compte est créé en attente et un e-mail d'activation est envoyé. Pour éviter les abus, le formulaire est limité à 10 essais par minute.

### Étape 2 : Activation

Le lien reçu par e-mail est **signé et valable 48 heures**, et ne sert qu'une fois. L'utilisateur choisit son mot de passe (8 caractères minimum, avec une majuscule et un chiffre) ; son compte est activé avec le profil prévu dans la liste des comptes autorisés. Si l'e-mail n'arrive pas, un bouton permet de le renvoyer.

### Étape 3 : Rattachement automatique

À l'activation, l'étudiant est automatiquement ajouté aux groupes organisationnels de sa classe et de ses matières pour l'année active. L'enseignant devient propriétaire des groupes des matières dont il est responsable.

### Étape 4 : Utilisation

L'utilisateur est connecté directement. Son accueil présente ses espaces (groupes de classe et de matière, groupes privés) avec le nombre de messages non lus, ses invitations en attente et ses notifications.

**Règle bloquante :** après 5 mots de passe incorrects, le compte est bloqué 15 minutes. Un compte inconnu et un mot de passe faux donnent le même message, pour ne rien révéler.

## 6. Gestion des espaces de communication

Chaque espace comporte : nom, description, type, propriétaire, date de création, membres et rôles, règle de visibilité des messages, mode de publication et état (actif, archivé).

### 6.1 Groupes organisationnels

Ils sont **générés automatiquement** par l'administration à partir des référentiels (§3.2) : un groupe par classe (propriétaire : l'administration) et un groupe par matière réunissant les étudiants de toutes les classes qui la suivent (propriétaire : l'enseignant responsable). Un aperçu indique ce qui sera créé. La génération peut être relancée **sans créer de doublon**, et le bouton **Synchroniser** ajoute à un groupe les membres manquants. Un étudiant ne peut pas quitter le groupe de sa classe.

### 6.2 Groupes privés

Un utilisateur disposant du droit **Créer des groupes privés** crée un groupe en cinq étapes (type, informations, visibilité des messages, invitations, récapitulatif) et en devient propriétaire. Il choisit qui peut publier (tous les membres, ou seulement les gestionnaires : mode annonce). Le groupe n'est visible que par ses membres.

### 6.3 Channels communautaires

Les channels existent dans le modèle de données et s'affichent pour leurs membres. Leur création, leur annuaire et les modes d'accès (ouvert, sur demande, restreint) sont prévus en version 1.1.

### 6.4 Cycle de vie d'un espace

États : Actif, Archivé (lecture seule : plus de messages ni de nouveaux membres). Les groupes organisationnels d'une année peuvent être archivés à l'activation de l'année suivante.

**Règle bloquante :** un espace ne reste jamais sans propriétaire. Si le propriétaire quitte l'espace, la propriété passe au plus ancien co-gestionnaire ou, à défaut, à l'administration.

## 7. Gestion des membres et des invitations

Le propriétaire et les co-gestionnaires peuvent :

- inviter un ou plusieurs utilisateurs (recherche par nom) ;
- consulter les invitations en attente et les annuler ;
- retirer un membre ;
- désigner ou retirer un co-gestionnaire ;
- transférer la propriété de l'espace.

L'utilisateur invité reçoit une notification et peut accepter ou refuser l'invitation. Une invitation non traitée **expire après 14 jours** (traitement automatique chaque nuit). Les demandes d'adhésion aux channels sont prévues en version 1.1.

**Règle bloquante :** un utilisateur retiré d'un espace perd immédiatement l'accès à ses messages et documents, y compris par lien direct : l'appartenance est vérifiée en base à chaque requête.

## 8. Messagerie et partage de contenus

Dans chaque espace dont il est membre, l'utilisateur peut :

- envoyer un message texte ;
- joindre des images (aperçu dans la conversation) ;
- joindre des documents (PDF, Word, Excel, PowerPoint, archives ZIP) ;
- répondre à un message précis ;
- modifier ou supprimer ses propres messages.

Chaque fichier affiche son nom, son format, sa taille et sa date d'expiration. Les nouveaux messages apparaissent automatiquement (rafraîchissement toutes les 5 secondes). **25 Mo maximum par fichier, 5 fichiers par message.** L'onglet Fichiers regroupant tous les documents d'un espace est prévu en version 1.1.

**Règle bloquante :** un fichier n'est accessible qu'aux membres de l'espace dans lequel il a été partagé. Le stockage est privé : chaque téléchargement passe par l'API, qui vérifie les droits à chaque accès.

## 9. Durée de visibilité des messages

La durée de visibilité est choisie à la création de l'espace, parmi les valeurs du §3.5 :

- **Illimitée** : les messages restent visibles tant que l'espace existe ;
- **Limitée** : chaque message devient invisible après la durée choisie (24 heures, 7 jours ou 30 jours), calculée à partir de sa date d'envoi.

Chaque message à durée limitée affiche sa date d'expiration (« Expire le … »), et l'espace affiche sa règle (ex. « Messages visibles 30 jours »).

**Règle bloquante :** la date d'expiration est fixée à l'envoi : une modification de la règle ne s'applique pas rétroactivement. Une pièce jointe expire en même temps que son message ; un message expiré n'est plus renvoyé par l'API et son fichier n'est plus téléchargeable. La suppression physique des données expirées par un traitement automatique est prévue en version 1.1.

## 10. Échanges pédagogiques

Les groupes de matière proposent :

- un **fil d'annonces** publié par l'enseignant, distinct de la discussion, avec notification des membres ;
- un espace **Ressources** où l'enseignant classe ses supports par chapitre ;
- un **Assistant IA** du cours, décrit ci-dessous.

**Assistant IA.** Quand l'enseignant dépose un PDF, son texte est extrait et indexé automatiquement en arrière-plan (badge « Indexé pour l'IA »). Quand un étudiant pose une question, l'application cherche les passages les plus pertinents **dans les supports de ce cours uniquement**, puis l'IA (Gemini, avec Groq en secours) rédige une réponse qui **cite le document et la page** ; un clic sur la source ouvre le document. Si le cours ne contient pas la réponse, l'assistant le dit au lieu d'inventer. Seul l'enseignant active l'assistant et peut lui donner une consigne. Chaque étudiant dispose de 20 questions par jour et peut noter chaque réponse (👍/👎). Seuls des extraits de cours sont envoyés à l'IA, jamais de données personnelles.

L'épinglage des messages est prévu en version 1.1.

## 11. Protection des utilisateurs et modération

**Confidentialité** (livrée) : les conversations, annonces, ressources et fichiers ne sont accessibles qu'aux membres de l'espace ; aucun utilisateur, y compris l'administration, ne consulte un groupe privé dont il n'est pas membre.

Le signalement des contenus et les outils de modération (masquer, supprimer, avertir, suspendre) sont prévus en version 1.1.

## 12. Notifications

Notifications dans l'application, avec compteur sur la cloche : invitation reçue, nouvelle annonce, nouvelle ressource, retrait d'un espace. Par e-mail : activation du compte, et annonces quand l'enseignant coche l'envoi par e-mail. La page Notifications permet de tout marquer comme lu. Les préférences de notification par espace sont prévues en version 1.1.

## 13. Historique et traçabilité

En version 1.0 sont conservés : les imports de comptes autorisés (auteur, fichier, lignes importées et ignorées, erreurs), les dates de dernière connexion et de blocage des comptes, et chaque échange avec l'Assistant IA (question, réponse, sources citées, fournisseur utilisé, avis). L'historique général des opérations (avec ancienne et nouvelle valeur) est prévu en version 1.1.

## 14. Tableau de bord

Le tableau de bord d'administration est prévu en version 1.1. En version 1.0, la page Groupes organisationnels indique pour chaque groupe son type, sa classe, son propriétaire, son nombre de membres et son état.

## 15. Fonctionnalités complémentaires

Réalisées en version 1.0 :

- **Assistant IA** du cours avec citation des sources (§10) ;
- **accessibilité** : lien « Aller au contenu », contrastes renforcés, navigation clavier ; score Lighthouse accessibilité 100 ;
- **interface responsive** : navigation en bas d'écran sur téléphone, dialogues en plein écran.

Prévues pour la suite : recherche dans les messages et documents, réactions, messagerie directe, modération automatique, interface multilingue, application installable (PWA), export des ressources en fin d'année.

## 16. Démarche de réalisation

L'équipe a appliqué une démarche Scrum :

- **Analyse des besoins et maquettes** : cas d'utilisation, maquette des écrans, matrice des permissions.
- **Planification** : backlog Jira (epics, user stories et critères d'acceptation, tickets SCRUM-xx), estimations, sprints.
- **Conception** : architecture, schéma de la base (32 tables), diagramme de classes, contrat d'API.
- **Collaboration avec GitHub** : une branche par ticket, pull requests relues par un autre membre, fusion seulement si l'intégration continue est verte.
- **Développement par itérations** et points d'équipe réguliers.
- **Tests** : environ 360 tests automatiques backend et 275 tests frontend lancés à chaque pull request, plus une recette manuelle par profil (plan de tests).
- **Documentation et démonstration** : README d'installation, guide utilisateur, guide de déploiement, scénario et vidéo de démonstration.

| Membre | Domaine principal |
|---|---|
| Chahredine Baccouche | Espaces, membres et invitations ; déploiement et intégration |
| Moataz Rabia | Inscription, activation, connexion |
| Mouetez Bouajila | Discussions et pièces jointes |
| Hayder Ramma | Ressources, chapitres, groupes organisationnels |
| Mansour Naili | Annonces et administration |
| Ayoub Boussoukaya | Assistant IA |

## 17. Contraintes techniques

Comment la version 1.0 respecte les contraintes :

- **Responsive** : interface Vue 3 + Vuetify adaptée à l'ordinateur, la tablette et le téléphone.
- **Sécurité** : jeton d'authentification (Laravel Sanctum), mots de passe hachés (bcrypt), droits vérifiés côté serveur sur chaque requête et chaque fichier, limitation des tentatives.
- **Architecture moderne** : front-end Vue 3 (Vercel), API REST Laravel 13 dans une image Docker (Render), base PostgreSQL (Neon), stockage objet privé compatible S3 (Supabase Storage), e-mails par l'API Resend.
- **Quasi temps réel** : les nouveaux messages et notifications sont récupérés automatiquement toutes les quelques secondes ; l'hébergement gratuit ne permet pas de serveur temps réel permanent.
- **Confidentialité** : accès limité aux membres, données personnelles jamais envoyées à l'IA, clés secrètes hors du code.
- **Extensibilité** : un seul modèle d'espace avec un type (organisationnel, privé, channel), services métier séparés, interface d'IA interchangeable.
- **Déploiement continu** : chaque fusion dans la branche principale est testée puis déployée automatiquement.

## 18. Résultat obtenu et livrables

Les utilisateurs disposent d'une application en ligne leur permettant de communiquer dans des espaces adaptés à l'organisation de l'université, de partager des contenus de cours et de gérer des groupes privés selon leurs droits, dans le respect des règles de visibilité et d'accès, avec un Assistant IA fondé sur les supports des enseignants.

| Livrable | Où le trouver |
|---|---|
| Application déployée | <https://uniconnect-six-lake.vercel.app> |
| Vidéo de démonstration | <https://chahredinebaccouch.github.io/uniconnect/> |
| Dépôt GitHub (code, pull requests et revues) | <https://github.com/ChahredineBACCOUCH/uniconnect> |
| Backlog Jira (user stories, sprints) | <https://uniconnectteam26.atlassian.net/jira/software/projects/SCRUM/boards/1> |
| Analyse, conception (schéma SQL, diagrammes), maquettes | `docs/conception/`, `docs/maquette/`, `docs/architecture.md`, `docs/api.md` |
| Plan et résultats de tests | `docs/tests.md`, `docs/plan-de-tests.md` |
| Guide d'installation et d'utilisation | `README.md`, `docs/installation.md`, `docs/guide-utilisateur.md` |
| Déploiement | `docs/deploiement.md` |
| Démonstration | `docs/demo.md`, `docs/demo-pas-a-pas.md` |

**Comptes de démonstration** (mot de passe `Demo1234!`) : `etudiant@demo.uniconnect`, `enseignant@demo.uniconnect`, `admin@demo.uniconnect`. L'API étant hébergée sur une offre gratuite, la première requête après 15 minutes d'inactivité peut prendre environ une minute.
