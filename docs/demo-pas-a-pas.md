# Démonstration v1.0 pas à pas : quoi cliquer, quoi écrire

Version détaillée du scénario de [demo.md](demo.md) : chaque clic, chaque champ, la valeur exacte à saisir et ce qui doit s'afficher.

Les valeurs ci-dessous ont été jouées en production le 18/11/2026 lors de l'enregistrement de la vidéo.

- Site : **https://uniconnect-six-lake.vercel.app**
- Mot de passe des comptes de démo : **`Demo1234!`**
- Notation : **« Bouton »** = bouton à cliquer · `valeur` = texte à saisir · ✅ = ce qui doit s'afficher

## Fichiers de test

Ils sont dans le dépôt, dossier [`docs/demo/fichiers/`](demo/fichiers/). **Copiez-les sur le bureau** de l'ordinateur de démonstration.

| Fichier | Sert à | Contenu |
|---|---|---|
| [`import-comptes-demo.xlsx`](demo/fichiers/import-comptes-demo.xlsx) | Étape 1.3 : import Excel des comptes autorisés | 6 lignes : 3 valides et 3 erreurs volontaires (voir ci-dessous) |
| [`synthese-chapitre-1.pdf`](demo/fichiers/synthese-chapitre-1.pdf) | Étape 3.4 : pièce jointe du message | Fiche de révision d'une page sur le modèle relationnel |

Contenu de `import-comptes-demo.xlsx` (première feuille, colonnes du modèle d'import) :

| Ligne | matricule | nom | prenom | email | profil | classe_code | Résultat attendu |
|---|---|---|---|---|---|---|---|
| 2 | ETU2026-201 | Mejri | Hiba | etu2026-201@uniconnect.test | ETUDIANT | L2-INFO-A | ✅ valide |
| 3 | ETU2026-202 | Saidi | Walid | etu2026-202@uniconnect.test | ETUDIANT | L2-INFO-B | ✅ valide |
| 4 | ENS2026-010 | Karray | Leila | ens2026-010@uniconnect.test | ENSEIGNANT | (vide) | ✅ valide |
| 5 | ETU2026-203 | Zribi | Omar | etu2026-203@uniconnect.test | ETUDIANT | M1-INEXISTANTE | ❌ classe inconnue |
| 6 | ETU2026-204 | Hamdi | Sana | adresse-invalide | ETUDIANT | L2-INFO-A | ❌ e-mail invalide |
| 7 | ETU2026-021 | Ayari | Ines | etu2026-021@uniconnect.test | ETUDIANT | L2-INFO-A | ❌ doublon (déjà autorisé) |

> Le fichier s'importe **une seule fois** : après la répétition, la remise à zéro de la base le rend de nouveau importable.

> Avant de commencer :
> - la base a été **remise à zéro** (voir demo.md) ;
> - le site a été ouvert 10 minutes avant, pour réveiller Render ;
> - la boîte mail du **compte Resend** est ouverte (téléphone de Moataz ou onglet).

---

## Étape 0 · Présentation (Chahredine, 1 min)

Rien à saisir. Écran : page de connexion du site.

Phrase : « UniConnect réunit les échanges de chaque classe et de chaque cours, avec un assistant qui répond à partir des supports des enseignants. »

---

## Étape 1 · Administration (Mansour, 1 min 30)

### 1.1 Connexion
Page de connexion :

| Champ | Valeur |
|---|---|
| Adresse électronique institutionnelle | `admin@demo.uniconnect` |
| Mot de passe | `Demo1234!` |

→ **« Se connecter »**
✅ « Bonjour Fatou ». Le menu de gauche affiche la section **ADMINISTRATION**.

### 1.2 Années universitaires
Menu → **« Années universitaires »**
✅ L'année **2026/2027** est « Active », avec « Plateforme ouverte ».

Phrase : « Une seule année est active : c'est elle qui ouvre les inscriptions et la génération des groupes. »

### 1.3 Comptes autorisés : recherche et import
Menu → **« Comptes autorisés »**
✅ La liste des **40** comptes autorisés.

| Champ | Valeur |
|---|---|
| Rechercher (« Nom ou matricule ») | `Ayari` |

✅ La liste est filtrée (Ines, Mehdi, Youssef Ayari). Effacer ensuite la recherche.

→ **« Importer »**
✅ Dialogue « Importer des comptes autorisés », étape **1 · Fichier** : « Choisir le fichier » et le bouton « Télécharger le modèle ».

| Champ | Valeur |
|---|---|
| Fichier Excel | sur le bureau : **`import-comptes-demo.xlsx`** |

→ **« Analyser le fichier »**
✅ Compteurs : **3** lignes valides · **1** doublon ignoré · **2** lignes en erreur. « Détail des erreurs » :
- Ligne 5 : « La classe « M1-INEXISTANTE » n'existe pas pour l'année universitaire active. »
- Ligne 6 : « L'adresse e-mail est invalide. »
- Ligne 7 : « Ce matricule figure déjà dans la liste des comptes autorisés. Cette adresse e-mail figure déjà dans la liste des comptes autorisés. »

Phrase : « La scolarité importe la liste depuis Excel. Chaque ligne est contrôlée avant d'écrire quoi que ce soit : les erreurs sont expliquées ligne par ligne, et les doublons sont ignorés. »

→ **« Continuer »**
✅ Étape **2 · Validation** : « **3** comptes seront importés. 3 lignes du fichier seront ignorées (doublons ou erreurs). » Récapitulatif : Fichier `import-comptes-demo.xlsx`, Lignes lues 6, Lignes valides 3, Lignes ignorées 3.

→ **« Importer 3 comptes »**
✅ Message : « 3 comptes importés. 3 lignes ignorées. ». La liste compte maintenant **43** entrées (Hiba Mejri, Walid Saidi et Leila Karray en « Non créé »).

### 1.4 Ajouter le compte qui servira à l'inscription
→ **« Ajouter »** (dialogue « Ajouter un compte autorisé »)

| Champ | Valeur |
|---|---|
| Matricule | `ETU2026-100` |
| E-mail institutionnel | **l'adresse du compte Resend** (la boîte ouverte sur le téléphone) |
| Nom | le nom de Moataz (ex. `Rabia`) |
| Prénom | `Moataz` |
| Profil | `Étudiant` (déjà sélectionné) |
| Classe | `L2-INFO-A — L2 Informatique A` |

→ **« Ajouter »**
✅ Message en bas : « Compte autorisé ajouté. »

### 1.5 Groupes organisationnels
Menu → **« Groupes organisationnels »**
✅ 9 groupes : 3 de classe et 6 de matière. « Bases de données » a pour propriétaire **Sophie Martin**.

→ **« Générer les groupes »**
✅ Dialogue « Générer les groupes de 2026/2027 » : « 0 à créer · 9 déjà existants ».

Phrase : « La génération est automatique et sans doublon : on peut la relancer sans risque. »

→ **« Annuler »**

### 1.6 Déconnexion
Icône **⟶|** en haut à droite (« Se déconnecter »).

---

## Étape 2 · Inscription avec e-mail (Moataz, 1 min 30)

### 2.1 Inscription refusée
Page de connexion → lien **« Créer un compte »**

| Champ | Valeur |
|---|---|
| Identifiant universitaire (matricule) | `ETU2026-999` |
| Nom | `Test` |
| Prénom | `Visiteur` |
| Adresse électronique institutionnelle | `test@uniconnect.test` |
| ☑ J'accepte la charte d'utilisation de la plateforme | cocher |

→ **« Créer mon compte »**
✅ En rouge sous l'e-mail : « Ce matricule n'est pas autorisé à créer un compte… ».

Phrase : « Seules les personnes déclarées par la scolarité peuvent créer un compte : le couple matricule et e-mail est vérifié. »

### 2.2 Inscription acceptée
Corriger les champs :

| Champ | Valeur |
|---|---|
| Identifiant universitaire (matricule) | `ETU2026-100` |
| Nom | le même nom qu'à l'étape 1.4 |
| Prénom | `Moataz` |
| Adresse électronique institutionnelle | **l'adresse du compte Resend** (la même qu'à l'étape 1.4) |
| ☑ Charte | déjà cochée |

→ **« Créer mon compte »**
✅ « **Vérifie ta boîte mail** · Un e-mail d'activation a été envoyé à … ».

### 2.3 L'e-mail et l'activation
Sur le téléphone ou dans l'onglet de la boîte mail :

✅ Un e-mail de **UniConnect <onboarding@resend.dev>**, objet « **Activez votre compte UniConnect** » (moins d'une minute ; regarder aussi « Spam » ou « Promotions »).

→ Bouton **« Activer mon compte »** dans l'e-mail
✅ La page « **Activer mon compte** » de UniConnect s'ouvre.

| Champ | Valeur |
|---|---|
| Nouveau mot de passe | `Uniconnect2026` (8 caractères minimum, avec majuscule, minuscule et chiffre) |
| Confirmer le mot de passe | `Uniconnect2026` |

→ **« Activer mon compte »**
✅ Moataz est connecté. Son menu affiche déjà **L2 Informatique A** et les 6 matières : il est rattaché automatiquement à sa classe.

Phrase : « Le compte s'active par un lien envoyé à l'adresse institutionnelle, puis l'étudiant retrouve automatiquement ses groupes. »

> Si l'e-mail n'arrive pas en 1 minute : sur l'écran « Vérifie ta boîte mail », ne rien recharger. Passer à l'étape 3 et revenir à l'e-mail plus tard. L'écran propose aussi « Renvoyer l'e-mail d'activation » quand l'envoi a échoué.

→ Se déconnecter.

---

## Étape 3 · L'espace étudiant (Mouetez, 2 min)

### 3.1 Connexion d'Amine
| Champ | Valeur |
|---|---|
| Adresse électronique institutionnelle | `etudiant@demo.uniconnect` |
| Mot de passe | `Demo1234!` |

→ **« Se connecter »**
✅ « Bonjour Amine » : messages non lus, **1 invitation en attente** et ses espaces.

### 3.2 Accepter l'invitation
Carte **Invitations** → ligne « Révisions BD – soutien · Invité par Sophie Martin » → **« Accepter »**
✅ Message : « Vous avez rejoint « Révisions BD – soutien ». »

### 3.3 Discussion du cours
Menu → **« Bases de données »** (onglet **Discussion**)
✅ 20 messages ; sous chaque message : « Expire le … ». En haut : « Messages visibles 30 jours ».

Faire défiler jusqu'au message de **Yasmine Trabelsi** → carte **fiche-revision-normalisation.pdf** → icône **⬇** (télécharger).

### 3.4 Envoyer un message avec un PDF
| Champ | Valeur |
|---|---|
| Votre message (« Écrire un message à Bases de données… ») | `Voici ma synthèse du chapitre 1 pour réviser le TP.` |

→ Trombone **📎** (« Joindre des fichiers ») → sur le bureau, choisir **`synthese-chapitre-1.pdf`** (fichier de test)
✅ Le fichier apparaît sous le champ : « synthese-chapitre-1.pdf ».

→ **« Envoyer »**
✅ Le message apparaît en bas (« Vous »), avec la carte **synthese-chapitre-1.pdf** (« PDF · 2,4 Ko ») et « Expire le … ».

Phrase : « Les fichiers restent privés : chaque téléchargement vérifie que la personne est membre, même avec un lien direct. »

### 3.5 Créer un groupe privé
Menu → **« + Créer un espace »**

| Étape | Champ | Valeur |
|---|---|---|
| 1 · Type | Quel type d'espace ? | **Groupe privé** (déjà sélectionné) → **« Continuer »** |
| 2 · Informations | Nom de l'espace | `Révisions TP noté` |
| | Description (facultative) | (vide) |
| | Droit de publication | **Tous les membres** → **« Continuer »** |
| 3 · Visibilité | Visibilité des messages | **7 jours (Par défaut)** → **« Continuer »** |
| 4 · Invitations | Rechercher par nom ou prénom | `Yas` → choisir **Yasmine Trabelsi** dans la liste → **« Continuer »** |
| 5 · Récapitulatif | — | vérifier : Groupe privé · Révisions TP noté · 7 jours · 1 invitation (Yasmine Trabelsi) → **« Créer l'espace »** |

✅ L'espace s'ouvre sur l'onglet **Membres**, avec un message de confirmation. Yasmine apparaît dans « Invitations en attente ».

→ Se déconnecter.

---

## Étape 4 · Côté enseignante (Hayder, 1 min 15)

### 4.1 Connexion de Sophie Martin
| Champ | Valeur |
|---|---|
| Adresse électronique institutionnelle | `enseignant@demo.uniconnect` |
| Mot de passe | `Demo1234!` |

### 4.2 Publier une annonce
Menu → **« Bases de données »** → onglet **Annonces** → **« Nouvelle annonce »**

| Champ | Valeur |
|---|---|
| Titre | `Salle changée : B14` |
| Contenu | `Le TP noté de jeudi aura lieu en salle B14, à 10 h comme prévu.` |
| Pièces jointes | (aucune) |
| ☐ Notifier aussi les membres par e-mail | **décocher** (sans domaine vérifié, Resend n'envoie qu'à l'adresse du compte Resend) |

→ **« Publier »**
✅ L'annonce apparaît en tête avec la mention « Nouveau ».

### 4.3 Ressources et réglage de l'assistant
Onglet **Ressources**
✅ 3 chapitres, chaque PDF avec le badge « Indexé pour l'IA », et le lien SQLZoo.

Onglet **Paramètres**
✅ « Assistant activé pour les membres », « 3 documents indexés », consigne : « Réponds en français, simplement, avec un exemple SQL ou PL/SQL quand c'est utile. »

Phrase : « Le cours devient la base de connaissances de l'assistant. L'enseignante garde la main. »

→ Se déconnecter.

---

## Étape 5 · Assistant IA (Ayoub, 3 min)

### 5.1 Connexion d'Amine et notification
Connexion avec `etudiant@demo.uniconnect` / `Demo1234!`.
✅ La cloche porte un compteur. Menu → **« Notifications »** → onglet **Notifications** : « **Salle changée : B14** », avec le texte de l'annonce.

### 5.2 Première question (chapitre 3)
Menu → **« Bases de données »** → onglet **« ✦ Assistant IA »**
✅ À droite : « Sources de l'assistant » (les 3 chapitres).

| Champ | Valeur |
|---|---|
| Votre question (« Posez une question sur le cours… ») | `Quels sont les attributs d'un curseur explicite ?` |

→ **« Demander »** (ou Entrée)
✅ Réponse : `%FOUND`, `%NOTFOUND`, `%ROWCOUNT`, `%ISOPEN` avec des marqueurs **[1]**, puis la source **« [1] Chapitre 3 – Les curseurs PL/SQL · p. 3 »** et « Réponse générée par IA, à vérifier dans les sources ».

→ Cliquer sur la puce **« Chapitre 3 – Les curseurs PL/SQL · p. 3 »**
✅ L'onglet **Ressources** s'ouvre, le document « Chapitre 3 – Les curseurs PL/SQL » est **mis en évidence**.

### 5.3 Deuxième question (chapitre 2)
Onglet **« Assistant IA »** :

| Champ | Valeur |
|---|---|
| Votre question | `Quelle est la différence entre la 3FN et la BCNF ?` |

→ **« Demander »**
✅ Source : **« Chapitre 2 – Dépendances fonctionnelles et normalisation · p. 3 »**.

### 5.4 Question hors du cours
| Champ | Valeur |
|---|---|
| Votre question | `Qui a gagné la Coupe du monde 2022 ?` |

→ **« Demander »**
✅ « Je n'ai pas trouvé de réponse fiable dans les ressources de cet espace. Essayez de reformuler, ou posez votre question dans la Discussion. »

→ Sous une réponse : **👍** (« Cette réponse vous a-t-elle aidé ? »). En bas : « Questions restantes aujourd'hui : 17 sur 20 ».

Phrase : « L'IA ne répond qu'à partir du cours, cite la page et dit quand elle ne sait pas. Elle utilise des services gratuits : Gemini, avec Groq en secours. »

---

## Étape 6 · Téléphone et bilan (Chahredine, 1 min 30)

Sur le téléphone : ouvrir **https://uniconnect-six-lake.vercel.app**, se connecter avec `etudiant@demo.uniconnect` / `Demo1234!`.

- ✅ Barre du bas : **Accueil · Espaces · Notifs**.
- **« Espaces »** → **« Bases de données »** : la discussion, avec le champ « Votre message » visible.
- Menu **☰** (en haut à gauche) : les espaces et « Se déconnecter ».

Bilan à dire :
- Scrum : Jira, sprints, PR relues ;
- qualité : CI GitHub, environ 360 tests backend et 275 frontend, Lighthouse accessibilité 100 ;
- suite **v1.1** : modération, tableau de bord, recherche, profil, mot de passe oublié.

Phrase : « Ce qui n'est pas encore livré est planifié, pas oublié. »

---

## Récapitulatif des valeurs

| Usage | Valeur |
|---|---|
| Administration | `admin@demo.uniconnect` / `Demo1234!` |
| Enseignante | `enseignant@demo.uniconnect` / `Demo1234!` |
| Étudiant | `etudiant@demo.uniconnect` / `Demo1234!` |
| Import Excel | fichier **`docs/demo/fichiers/import-comptes-demo.xlsx`** → 3 valides, 1 doublon, 2 erreurs → « Importer 3 comptes » |
| Inscription refusée | `ETU2026-999` · `Test` · `Visiteur` · `test@uniconnect.test` |
| Inscription acceptée | `ETU2026-100` · nom · `Moataz` · adresse du compte Resend |
| Mot de passe choisi à l'activation | `Uniconnect2026` |
| Message de discussion | `Voici ma synthèse du chapitre 1 pour réviser le TP.` + **`docs/demo/fichiers/synthese-chapitre-1.pdf`** |
| Groupe privé | `Révisions TP noté` · 7 jours · invitation `Yas` → Yasmine Trabelsi |
| Annonce | `Salle changée : B14` / `Le TP noté de jeudi aura lieu en salle B14, à 10 h comme prévu.` |
| Questions IA | `Quels sont les attributs d'un curseur explicite ?` · `Quelle est la différence entre la 3FN et la BCNF ?` · `Qui a gagné la Coupe du monde 2022 ?` |
