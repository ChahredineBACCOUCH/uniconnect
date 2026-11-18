# Scénario de démonstration : jeudi 19/11/2026

**Durée : 15 minutes maximum** (12 min de démo + 3 min de questions), en production, sur un seul ordinateur relié au projecteur. Ticket : SCRUM-187.

> **Version détaillée** (chaque clic et la valeur exacte de chaque champ) : [demo-pas-a-pas.md](demo-pas-a-pas.md).

- Application : https://uniconnect-six-lake.vercel.app
- État de l'API : https://uniconnect-api-qov1.onrender.com/api/health
- Dépôt et documentation : https://github.com/ChahredineBACCOUCH/uniconnect

## Comptes de démonstration

Ces comptes sont créés par `DemoProductionSeeder` (SCRUM-188, voir « Charger les données » plus bas).

| Fenêtre | Personne | E-mail | Mot de passe |
|---|---|---|---|
| Étudiant (L2 Informatique A) | Amine Benali | `etudiant@demo.uniconnect` | `Demo1234!` |
| Enseignante (Bases de données) | Sophie Martin | `enseignant@demo.uniconnect` | `Demo1234!` |
| Administration | Fatou Diallo | `admin@demo.uniconnect` | `Demo1234!` |
| Visiteur | (déconnecté) | — | — |

**Données prêtes pour la démo :**
- Groupe de matière **« Bases de données »** (Sophie Martin, Amine et 4 camarades) :
  - 3 PDF indexés pour l'Assistant IA :
    - Chapitre 1 : le modèle relationnel ;
    - Chapitre 2 : normalisation ;
    - Chapitre 3 : curseurs PL/SQL ;
  - un lien d'exercices ;
  - une discussion de 20 messages, dont une fiche de révision en PDF ;
  - 2 annonces, dont « TP noté jeudi 20/11 ».
- Groupe privé **« Projet intégration – Équipe 4 »** et channel **« Club Développement web »**.
- **Invitation en attente** pour Amine (« Révisions BD – soutien », de Sophie Martin) et des notifications non lues.

## Avant la démo (J-1 et le matin)

- [ ] Données de démo chargées en production (une seule fois, voir plus bas)
- [ ] 18/11 : répétition chronométrée avec toute l'équipe (SCRUM-189), inscription comprise (`ETU2026-100` + adresse du compte Resend, voir « Inscription avec e-mail »)
- [ ] **Base remise à zéro** après la répétition (`RESET_DEMO=EFFACER-TOUT`, puis **retour à `non`** aussitôt, voir plus bas)
- [ ] Boîte mail du **compte Resend** ouverte sur le téléphone de Moataz (ou dans un onglet) pour montrer l'e-mail d'activation
- [ ] 18/11 : enregistrement de la **vidéo du plan B** (tout le scénario, en cas de panne réseau)
- [ ] Le matin : ouvrir `/api/health` pour réveiller Render (environ 1 min ; l'application affiche « Le serveur se réveille »), puis vérifier `db: ok`
- [ ] 4 fenêtres prêtes (navigation privée ou profils séparés) : **Visiteur**, **Amine**, **Sophie Martin**, **Fatou Diallo**
- [ ] Téléphone prêt avec Amine connecté, pour la version mobile
- [ ] Un petit PDF sur le bureau, à joindre à un message
- [ ] Si la répétition a fait accepter l'invitation « Révisions BD – soutien » : Sophie invite Amine dans un autre groupe privé, pour en avoir une en attente

## Déroulé

| Temps | Qui présente | Fenêtre | Ce qu'on montre | Phrase clé |
|---|---|---|---|---|
| 0:00 – 1:00 | Chahredine | — | Le problème : la communication d'une université est dispersée (e-mails, WhatsApp, plateformes). | « UniConnect réunit les échanges de chaque classe et de chaque cours, avec un assistant qui répond à partir des supports des enseignants. » |
| 1:00 – 2:30 | Mansour | Administration | **Années universitaires** (une seule active). **Comptes autorisés** : liste, recherche, **import Excel** avec aperçu des erreurs (modèle téléchargeable), puis **Ajouter** le compte de Moataz : matricule `ETU2026-100`, l'adresse du **compte Resend**, Étudiant, L2 Informatique A. **Groupes organisationnels** : génération automatique. | « Les espaces de classe et de matière sont créés automatiquement à partir des données de la scolarité. » |
| 2:30 – 4:00 | Moataz | Visiteur + téléphone | **Inscription** avec `ETU2026-999` / `test@uniconnect.test` : refusée (matricule non autorisé). Puis `ETU2026-100` + l'adresse du compte Resend : « Vérifie ta boîte mail ». **L'e-mail « UniConnect » arrive** (téléphone ou onglet) → clic sur le lien → choix du mot de passe → connecté, déjà membre de « L2 Informatique A » et de ses matières. | « Seules les personnes déclarées par la scolarité peuvent créer un compte : le couple matricule + e-mail est vérifié, et le compte s'active par un lien envoyé à l'adresse institutionnelle. » |
| 4:00 – 6:00 | Mouetez | Étudiant | Accueil (non-lus, cloche) → **accepter l'invitation** (confirmation affichée) → « Bases de données » → Discussion → télécharger la fiche de révision → envoyer un message **avec un PDF** → « Messages visibles 30 jours » → **créer un groupe privé** en invitant Yasmine Trabelsi. | « Les messages expirent selon la règle du groupe. Un non-membre ne peut ni lire ni télécharger, même avec le lien direct : c'est vérifié à chaque téléchargement. » |
| 6:00 – 7:30 | Hayder | Enseignante | **Publier une annonce** (« Salle changée : B14 ») → la notification arrive chez Amine. **Ressources** : chapitres, PDF, badge « Indexé pour l'IA ». **Paramètres** : réglage de l'Assistant IA. | « Le cours devient la base de connaissances de l'assistant. L'enseignant garde la main. » |
| 7:30 – 10:30 | **Ayoub** | Étudiant | **Assistant IA** : « Quels sont les attributs d'un curseur explicite ? » → réponse avec `[1] Chapitre 3 – Les curseurs PL/SQL · p. 3` → **clic sur la source** : Ressources avec le document en évidence. « Quelle est la différence entre la 3FN et la BCNF ? ». Question hors sujet (« Qui a gagné la Coupe du monde 2022 ? ») : l'assistant dit que le cours n'en parle pas. Vote 👍, quota restant. | « L'IA ne répond qu'à partir du cours, cite la page, et dit quand elle ne sait pas. Elle utilise des services gratuits (Gemini, Groq en secours). » |
| 10:30 – 12:00 | Chahredine | Téléphone | Même parcours sur mobile (barre de navigation du bas, Discussion), puis bilan : Scrum (Jira, sprints, PR relues), qualité (CI, ~350 tests backend et ~275 frontend, Lighthouse accessibilité 100), suite **v1.1**. | « Ce qui n'est pas encore livré est planifié, pas oublié. » |
| 12:00 – 15:00 | Toute l'équipe | — | Questions | — |

### Périmètre affiché : seulement la v1.0

L'interface n'affiche que ce qui est livré. Ces fonctionnalités prévues en v1.1 sont **masquées**, pas présentées comme « bientôt disponibles » :
- recherche globale, profil, annuaire des channels, création de channel ;
- onglets Fichiers et Épinglés ;
- demandes d'adhésion, signalement et modération ;
- mot de passe oublié, création manuelle de groupe ;
- tableau de bord d'administration et résumé de discussion par l'IA.

Si le jury pose la question, répondre avec la feuille de route v1.1 (Jira).

### Inscription avec e-mail : à savoir

Les e-mails partent par l'**API de Resend** (`MAIL_MAILER=resend`, clé dans `MAIL_PASSWORD`). L'offre gratuite de Render bloque le SMTP. Envoi vérifié en production le 18/11 : inscription, e-mail reçu, compte activé.

- **Une seule adresse peut recevoir** : sans domaine vérifié dans Resend, l'expéditeur est `onboarding@resend.dev` et seule l'adresse du **compte Resend** reçoit les e-mails. Le compte ajouté en direct par l'administration doit donc utiliser cette adresse.
- **Une adresse ne sert qu'une fois** : après la répétition, la base **doit** être remise à zéro. Sinon l'adresse du compte Resend est déjà utilisée, et comme c'est la seule qui reçoit les e-mails, l'inscription ne peut pas être refaite.
- **L'e-mail met en général moins d'une minute.** Regarder aussi « Spam » ou « Promotions ». S'il n'arrive pas, l'écran propose « Renvoyer l'e-mail d'activation ». En dernier recours, continuer la démo avec le compte d'Amine (déjà actif).
- Le lien est valable **48 heures** et ne sert qu'une fois.

## Charger les données de démo en production (une seule fois)

Le Shell Render n'existe pas en offre gratuite. Le chargement se fait donc au démarrage du conteneur (`backend/docker/start.sh`) :

1. Render → service **uniconnect-api** → **Environment** → ajouter `SEED_DEMO` = `true` → *Save, rebuild and deploy*.
2. Attendre « Your service is live », puis vérifier qu'Amine voit « Bases de données » et ses 3 PDF.
3. **Retirer** la variable `SEED_DEMO` (sinon le seeder s'exécute à chaque redémarrage, sans doublon mais inutilement).

Le seeder est **idempotent** (relancé, il ne crée aucun doublon) et ne supprime rien. Les PDF de démo sont dans `backend/database/seeders/demo/` (générés par `generer_pdf.py`).

**Repartir d'une base propre** (irréversible, efface aussi les données de l'équipe) : `RESET_DEMO` = `EFFACER-TOUT` sur Render, redéployer, vérifier, puis **retirer la variable**. Seules les données de démo restent. Pratique après la répétition du 18/11 pour retrouver l'invitation en attente, les non-lus et un quota IA neuf.

## Si quelque chose ne marche pas

| Problème | Réaction immédiate |
|---|---|
| Render ne répond pas (service endormi) | L'application affiche « Le serveur se réveille » : attendre 30 à 60 secondes en parlant du parcours. |
| L'IA répond « momentanément indisponible » | Expliquer la bascule automatique Gemini → Groq, réessayer une fois, puis passer à la vidéo du plan B pour cette partie. |
| Quota de questions atteint (20 par jour) | Montrer le message (comportement prévu), puis la vidéo. |
| Pas de réseau | Vidéo du plan B, en commentant en direct. |
| Bug inattendu | Ne pas déboguer en direct : « on le note » et passer à l'étape suivante. |

## Questions probables du jury (réponses préparées)

- **Pourquoi pas de temps réel (WebSocket) ?** L'offre gratuite de Render ne permet pas de serveur temps réel. Un rafraîchissement toutes les 5 secondes suffit pour une messagerie de cours.
- **Que se passe-t-il si l'IA invente ?** La consigne lui impose de répondre uniquement à partir des extraits fournis et de citer leurs numéros. S'il n'y a aucun extrait pertinent, on n'appelle même pas l'IA. Les questions de test (`docs/ia-evaluation.md`) mesurent la justesse des sources.
- **Et les données personnelles ?** On n'envoie à l'IA que des extraits de cours. Les clés sont dans les variables d'environnement, jamais dans Git. Les droits sont vérifiés côté serveur.
- **Où sont stockés les fichiers ?** Dans un stockage objet privé compatible S3 (Supabase Storage). Ils ne sont jamais publics : chaque téléchargement passe par l'API, qui vérifie l'appartenance au groupe.
- **Pourquoi certaines fonctions manquent ?** La livraison a été avancée d'une semaine. Nous avons choisi de livrer un cœur complet et testé plutôt que tout à moitié. La v1.1 est déjà planifiée dans Jira.
