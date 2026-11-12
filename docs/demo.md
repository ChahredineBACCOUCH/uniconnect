# Scénario de démonstration — jeudi 19/11/2026

**Durée : 15 minutes maximum** (12 min de démo + 3 min de questions) · en production · un seul ordinateur relié au projecteur.

## Avant la démo (J-1 et le matin)

- [ ] 18/11 : répétition chronométrée avec toute l'équipe (SCRUM-189)
- [ ] 18/11 : enregistrement de la **vidéo du plan B** (tout le scénario, en cas de panne réseau)
- [ ] Le matin : ouvrir `/api/health` pour réveiller Render, puis vérifier `db: ok`
- [ ] 4 fenêtres prêtes, chacune connectée, en navigation privée : **Visiteur**, **Yassine** (étudiant), **Mme Ben Ali** (enseignante), **Leila** (administration)
- [ ] Téléphone prêt avec Yassine connecté, pour montrer la version mobile
- [ ] Données de démo chargées (SCRUM-188) : classe L2 Info A, cours « Bases de données », PDF « Chapitre 3 – Normalisation » indexé
- [ ] Compte `rania.gharbi@univ.tn` présent dans les comptes autorisés, mais **pas encore créé**

## Déroulé

| Temps | Qui présente | Écran | Ce qu'on montre | Phrase clé |
|---|---|---|---|---|
| 0:00 – 1:00 | Chahredine | — | Le problème : la communication d'une université est dispersée (e-mails, WhatsApp, plateformes) | « UniConnect réunit les échanges de chaque classe et de chaque cours, avec un assistant qui répond à partir des supports des enseignants. » |
| 1:00 – 3:00 | Mansour | Admin | Années → **import Excel** des comptes autorisés (montrer une erreur détectée à l'aperçu) → **génération des groupes** | « Les espaces de classe et de matière sont créés automatiquement à partir des données de la scolarité. » |
| 3:00 – 4:30 | Moataz | Visiteur | Inscription de Rania : e-mail vérifié → lien d'activation → mot de passe → elle retrouve **automatiquement** ses groupes | « Seules les personnes autorisées peuvent créer un compte. » |
| 4:30 – 6:30 | Mouetez | Étudiant | Accueil (non-lus) → discussion « Bases de données » → message + **PDF joint** → reçu en direct chez un autre compte → groupe privé + invitation | « Un non-membre ne peut ni lire ni télécharger, même avec le lien direct. » (montrer la 403) |
| 6:30 – 8:00 | Hayder | Enseignant | Annonce publiée (notification) → Ressources : ajout d'un PDF dans un chapitre → puce « Indexée pour l'IA » → activation de l'assistant | « Le cours devient la base de connaissances de l'assistant. » |
| 8:00 – 11:00 | **Ayoub** | Étudiant | **Assistant IA** : « Quelle est la différence entre la 2FN et la 3FN ? » → réponse + **sources cliquables** (page du PDF) → question hors sujet → refus → 👍 | « L'IA ne répond qu'à partir du cours, cite ses sources, et dit quand elle ne sait pas. Elle utilise des services gratuits (Gemini, Groq en secours). » |
| 11:00 – 12:00 | Chahredine | Téléphone | Même parcours sur mobile, puis bilan : organisation Scrum (Jira, 4 sprints, PR relues) et suite **v1.1** (modération, tableau de bord, recherche…) | « Ce qui n'est pas encore livré est planifié, pas oublié. » |
| 12:00 – 15:00 | Toute l'équipe | — | Questions | — |

## Si quelque chose ne marche pas

| Problème | Réaction immédiate |
|---|---|
| Render ne répond pas (service endormi) | Recharger la page après 30 secondes, en parlant du parcours en attendant |
| L'IA répond 503 (quota épuisé) | Montrer le message « momentanément indisponible » (prévu), puis passer à la vidéo du plan B pour cette partie |
| Pas de réseau | Vidéo du plan B, en commentant en direct |
| Bug inattendu | Ne pas déboguer en direct : « on le note » et passer à l'étape suivante |

## Questions probables du jury (réponses préparées)

- **Pourquoi pas de temps réel (WebSocket) ?** L'offre gratuite de Render ne permet pas de serveur temps réel. Un rafraîchissement toutes les 5 secondes suffit pour une messagerie de cours.
- **Que se passe-t-il si l'IA invente ?** La consigne lui impose de répondre uniquement à partir des extraits fournis et de citer leurs numéros. S'il n'y a aucun extrait pertinent, on n'appelle même pas l'IA. Les 10 questions de test (SCRUM-180) mesurent la justesse des sources.
- **Et les données personnelles ?** On n'envoie à l'IA que des extraits de cours. Les clés sont dans les variables d'environnement, jamais dans Git. Les droits sont vérifiés côté serveur.
- **Pourquoi certaines fonctions manquent ?** La livraison a été avancée d'une semaine. Nous avons choisi de livrer un cœur complet et testé plutôt que tout à moitié. La v1.1 est déjà planifiée dans Jira.
