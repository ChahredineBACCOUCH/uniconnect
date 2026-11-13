# Plan de tests — recette finale

Ce plan dit **quoi tester**, **avec quel profil** et **ce qu'on doit obtenir**. C'est la référence de la recette finale : chacun s'en sert pour vérifier les fonctions qu'il n'a pas développées.

| Échéance | Date |
|---|---|
| Gel du code | 17/11 à 18 h |
| Recette manuelle (en production) | 18/11 |
| Livraison et démonstration | 19/11 |
| Daily de présentation et relecture du plan | **21/11** |

Documents liés :

- [`plan-de-tests.md`](plan-de-tests.md) : comptes de test, liste des règles bloquantes (R1–R19) et check-list manuelle par profil ;
- [`api.md`](api.md) : **contrat de l'API** (routes, corps, codes d'erreur) — c'est la référence pour le résultat attendu ;
- [`guide-utilisateur.md`](guide-utilisateur.md) : le mode d'emploi à suivre pendant la recette ;
- [`conception/schema.sql`](conception/schema.sql) : le schéma attendu.

Environnement de la recette : production <https://uniconnect-six-lake.vercel.app> (API : <https://uniconnect-api-qov1.onrender.com/api/health> → `"db": "ok"`).
Les comptes et le mot de passe de test sont dans [`plan-de-tests.md` § 1](plan-de-tests.md) : le mot de passe est partagé **en privé dans l'équipe**, jamais écrit dans le dépôt (qui est public).

> Chaque résultat attendu est vérifié **deux fois** : dans l'interface (ce que la personne voit) et dans l'API (code HTTP + message en français). La sécurité réelle est côté serveur : une interface qui cache un bouton ne suffit pas.

## 1. Convention du tableau

| Code | Signification |
|---|---|
| **OK** | le parcours fonctionne : la page s'affiche, les données attendues sont là, le comportement est conforme |
| **403** | accès refusé : page « Forbidden » ou message « vous n'avez pas le droit » |
| **404** | « Introuvable » (fonctionnalité désactivée, ressource absente) |
| **422** | message d'erreur **en français** sous le champ du formulaire |
| **423** | compte bloqué (trop d'échecs de connexion) |
| **429** | quota atteint (20 questions par jour) ou trop de requêtes |
| **caché** | l'action n'est pas affichée pour ce profil (et l'API la refuse aussi si on force l'URL) |
| **à faire** | non couvert par un test automatique : il faut l'écrire ou le tester à la main |

## 2. Parcours × profils

Une ligne par parcours, une colonne par profil. La cellule donne le **résultat attendu**.

| # | Parcours | Étudiant | Enseignant | Administration |
|---|---|---|---|---|
| P1 | **Inscription** (E02) | **OK** si l'e-mail est dans la liste des comptes autorisés (e-mail reçu) ; sinon **422** sous le champ e-mail | **OK** — le profil (étudiant / enseignant) est déduit de la liste des comptes autorisés | **OK** — profil déduit ; ⚠️ les droits d'administration ne sont **pas** accordés à l'inscription (ils viennent du seeder de démo) : à vérifier |
| P2 | **Activation** (E03) | **OK** : lien signé → mot de passe → rattachement automatique au groupe de sa classe et aux groupes de ses matières | **OK** : devient propriétaire des groupes des matières dont il est responsable | **OK** : aucun rattachement automatique |
| P3 | **Connexion** (E01) et blocage | **OK** (jeton) ; 5 échecs → **423** « bloqué 15 minutes » ; lien d'activation expiré → refusé | idem | idem |
| P4 | **Accueil / mes espaces** | ses espaces seulement (groupes de classe + de matière), compteurs de non-lus justes | + les groupes dont il est propriétaire et ses groupes privés | tous les espaces ; section « Administration » visible |
| P5 | **Discussion** (messages) | lire et envoyer ; en **mode annonce** : bouton de publication **caché** et **403** ; message expiré : invisible ; message d'un autre : modification/suppression **403** | publier ; modifier et supprimer **son** message ; celui d'un autre : **403** ; espace archivé : **403** | comme l'enseignant s'il est membre de l'espace, sinon **403** |
| P6 | **Pièces jointes** | joindre un PDF (≤ 25 Mo — règle `regles_plateforme`, formats autorisés) → sinon **422** ; plus de 5 fichiers → **422** ; **téléchargement : voir § 5** (SCRUM-137, non livré) | idem | idem |
| P7 | **Groupe privé** | bouton « Créer un groupe privé » **caché** sans le droit `CREER_GROUPE_PRIVE` (défaut des comptes de démo) ; sinon **403** en API ; peut quitter un espace | créer, renommer, supprimer (propriétaire) ; nom trop court → **422** | idem |
| P8 | **Membres et rôles** | voir la liste et les rôles ; pas de bouton « retirer » / « nommer co-gestionnaire » (**caché**, **403** en API) | nommer un co-gestionnaire ; retirer un membre → **accès coupé immédiatement** (**403**) ; transférer la propriété ; le propriétaire qui part sans successeur → **409** | peut reprendre un espace quand il n'y a plus de co-gestionnaire |
| P9 | **Invitation** | accepter / refuser une invitation reçue (notification reçue dès la fusion de SCRUM-121) ; invitation expirée → **422** ; seule l'invitation reçue est acceptable (**403** sinon) | inviter des membres (propriétaire / co-gestionnaire) ; simple membre → **403** ; groupe organisationnel → **422** ; annulable par son auteur ou le propriétaire | idem |
| P10 | **Annonce** | lire les annonces ; publier → **403** (bouton **caché**) | publier → **201** et les membres sont **notifiés** (+ e-mail si `notifier_email`) ; supprimer **sa** annonce, pas celle d'un autre → **403** | idem |
| P11 | **Chapitres et ressources** | lire et **télécharger** les ressources ; modifier / supprimer **caché** ; non-membre → **403** | créer un chapitre, ajouter un PDF (→ « Indexée pour l'IA ») ou un lien, modifier, déplacer, supprimer ; membre simple → **403** | idem |
| P12 | **Assistant IA** | poser une question → réponse + sources cliquables ; assistant inactif → **404** ; 21ᵉ question du jour → **429** ; question hors sujet → « pas de source » ; hors espace → **403** | activer / désactiver l'assistant et sa consigne (onglet visible **seulement** s'il est actif) ; simple membre → **403** | idem |
| P13 | **Notifications** | lister, marquer lue, tout lire — ⚠️ à ne tester qu'**après** la fusion de SCRUM-121 (PR ouverte) | idem | idem |
| P14 | **Channel** | v1.1 (§ 5) | v1.1 | v1.1 |
| P15 | **Signalement** | v1.1 (§ 5) | v1.1 | v1.1 |
| P16 | **Modération** | v1.1 (§ 5) | v1.1 | v1.1 |
| P17 | **Administration** | **403** sur toutes les routes `/admin/...` et section « Administration » **cachée** dans le menu | **403** | **OK** : années (une seule active), référentiels, comptes autorisés, import Excel, groupes organisationnels ; élément encore utilisé → **409** ; suppression impossible sans « désactiver » |
| P18 | **Mobile** (390 px) | navigation basse, aucun texte coupé, message envoyable au doigt | onglets de l'espace utilisables au doigt | tableaux d'administration défilables |

### Points à vérifier en priorité (bloquants pour la démonstration)

- [ ] **P1–P3** : une personne autorisée peut créer son compte, l'activer et se connecter ; une personne non autorisée est refusée.
- [ ] **P5** : le message envoyé par un étudiant apparaît chez l'autre membre **en moins de 10 secondes** (rafraîchissement toutes les 5 s).
- [ ] **P8** : un membre retiré perd l'accès **immédiatement** (messages, fichiers, onglet Assistant IA).
- [ ] **P12** : la réponse de l'IA cite une source qui ouvre le bon PDF.
- [ ] **P17** : un étudiant ne voit **aucun** écran d'administration.
- [ ] **P18** : le parcours complet refait sur téléphone.

## 3. Règles bloquantes de l'énoncé

Chaque règle a **un** test automatique qui la couvre. La colonne « Test automatique » donne le fichier et la méthode (dans `backend/tests/`). « à faire » = aucun test automatique ne la couvre aujourd'hui.

| # | Règle | Résultat attendu | Test automatique | État |
|---|---|---|---|---|
| R1 | Inscription **contrôlée** : l'e-mail doit être dans la liste des comptes autorisés | **422**, aucun compte créé | `Feature/Auth/RegisterTest.php::test_un_matricule_absent_de_la_liste_des_comptes_autorises_est_refuse` · `Feature/EligibiliteInscriptionTest.php::test_hors_liste_des_comptes_autorises_le_refus_est_non_autorise` | ✅ |
| R2 | Compte déjà créé pour ce matricule | **409** | `Feature/Auth/RegisterTest.php::test_un_compte_deja_cree_pour_ce_matricule_est_refuse` | ✅ |
| R3 | Pas d'année universitaire active | **422** (« plateforme fermée ») | `Feature/Auth/RegisterTest.php::test_sans_annee_universitaire_active_le_refus_est_plateforme_fermee` | ✅ |
| R4 | Charte non acceptée | **422** | `Feature/Auth/RegisterTest.php::test_la_charte_doit_etre_acceptee_pour_creer_un_compte` | ✅ |
| R5 | Lien d'activation expiré ou signature falsifiée | refusé, aucun accès | `Feature/Auth/ActivationTest.php::test_un_lien_expire_est_refuse` · `Feature/EligibiliteInscriptionTest.php::test_un_tampon_de_signature_invalide_est_rejete` | ✅ |
| R6 | Mot de passe trop faible à l'activation | **422** | `Feature/Auth/ActivationTest.php::test_un_mot_de_passe_trop_faible_est_refuse` | ✅ |
| R7 | 5 mots de passe incorrects | **423** pendant 15 min dès le 5ᵉ essai | `Feature/Auth/LoginTest.php::test_cinq_echecs_bloquent_le_compte_pendant_quinze_minutes` | ✅ |
| R8 | Compte désactivé ou suspendu | connexion refusée | `Feature/Auth/LoginTest.php::test_un_compte_desactive_ou_suspendu_ne_peut_plus_se_connecter` | ✅ |
| R9 | **Accès retiré immédiatement** : un membre retiré perd messages, fichiers, IA et l'espace | **403** dès la requête suivante | `Feature/Espaces/RetraitMembreTest.php::test_un_membre_retire_perd_immediatement_l_acces_aux_messages` | ✅ |
| R10 | **Téléchargement protégé** : une ressource n'est téléchargeable que par un membre de l'espace | **403** hors espace | `Feature/Espaces/ChapitresRessourcesTest.php::test_telecharger_une_ressource_fichier_ou_lien` (inclut le cas non-membre → 403) | ✅ |
| R11 | **Purge effective** : les messages et fichiers expirés sont supprimés, pas seulement masqués | index `messages_a_purger` présent, mais **aucune commande de purge** (table `traitements_expiration` non créée) | — | ⚠️ hors périmètre du 19/11 (non livré) |
| R12 | **Une seule année active** | l'activation d'une année désactive l'autre | `Feature/Admin/AnneeTest.php::test_activer_une_annee_desactive_l_ancienne_et_il_n_en_reste_qu_une_active` · `Feature/AdminAnneesTest.php::test_l_activation_desactive_l_ancienne_annee` | ✅ |
| R13 | **Propriétaire unique** par espace | jamais deux propriétaires | `Feature/EspacesAdhesionsTest.php::test_un_espace_ne_peut_avoir_qu_un_seul_proprietaire` · `Feature/MembreApiTest.php::test_transferer_la_propriete_laisse_un_seul_proprietaire` · `Feature/Admin/GenerationGroupesTest.php::test_changer_l_enseignant_ne_cree_pas_un_second_proprietaire` | ✅ |
| R14 | **Dernier gestionnaire** : le propriétaire qui part laisse l'espace au plus ancien co-gestionnaire, sinon à l'administration ; sans personne possible | **409** | `Feature/MembreApiTest.php::test_sans_successeur_possible_le_proprietaire_ne_peut_pas_partir` · `::test_sans_co_gestionnaire_l_administration_reprend_l_espace` · `::test_le_proprietaire_qui_part_transmet_au_plus_ancien_co_gestionnaire` | ✅ |
| R15 | Un membre ne peut pas inviter ; un groupe organisationnel n'est pas invitable | **403** / **422** | `Feature/InvitationApiTest.php::test_un_simple_membre_ne_peut_pas_inviter` · `::test_on_ne_peut_pas_inviter_dans_un_groupe_organisationnel` | ✅ |
| R16 | Invitation : doublons signalés, expiration à 14 jours, seul l'invité décide | `creees`, `deja_invites` ; **422** si expirée | `Feature/InvitationApiTest.php::test_le_proprietaire_invite_et_les_doublons_sont_signales` · `::test_une_invitation_expiree_n_est_pas_acceptable` · `::test_seul_l_invite_peut_accepter_ou_refuser` | ✅ |
| R17 | Un non-membre ne lit ni messages, ni fichiers, ni annonces, ni ressources | **403** | `Feature/MessageApiTest.php::test_un_non_membre_ne_peut_pas_lire_les_messages` · `Feature/AnnonceApiTest.php::test_un_non_membre_ne_peut_pas_lire_les_annonces` · `Feature/Espaces/ChapitresRessourcesTest.php::test_un_non_membre_ne_voit_ni_chapitres_ni_ressources` | ✅ |
| R18 | Mode annonce : seuls les gestionnaires publient | **403** pour un membre simple | `Feature/MessageApiTest.php::test_un_membre_simple_ne_peut_pas_publier_en_mode_annonce` · `Feature/AnnonceApiTest.php::test_un_etudiant_membre_recoit_403_en_publiant_une_annonce` | ✅ |
| R19 | Espace archivé : plus d'écriture | **403** | `Feature/MessageApiTest.php::test_l_envoi_est_refuse_dans_un_espace_archive` | ✅ |
| R20 | Durée de visibilité des messages (7 jours par défaut, illimitée ou courte autorisée) | `expire_le` calculé, message invisible après expiration | `Feature/Espaces/DiscussionTest.php::test_un_message_expire_apres_la_duree_de_visibilite_de_sept_jours` · `Feature/MessageApiTest.php::test_l_envoi_calcule_l_expiration_avec_la_duree_de_l_espace` | ✅ |
| R21 | Seul l'auteur modifie ou supprime son message | **403** pour les autres | `Feature/MessageApiTest.php::test_un_autre_membre_ne_peut_pas_modifier_ni_supprimer_le_message` | ✅ |
| R22 | Fichier trop gros (> 25 Mo), format interdit ou plus de 5 fichiers | **422** avec message en français | `Feature/Espaces/PiecesJointesTest.php::test_un_fichier_de_trente_mo_est_refuse_avec_un_message_clair` · `::test_un_format_de_fichier_non_autorise_est_refuse` · `::test_plus_de_cinq_fichiers_sont_refuses` | ✅ |
| R23 | Un étudiant n'accède à aucune route `/admin/...` | **403** | `Feature/AdminAnneesTest.php::test_un_utilisateur_sans_le_droit_est_refuse` · `Feature/Admin/GenerationGroupesTest.php::test_un_utilisateur_sans_le_droit_ne_peut_pas_generer` | ✅ |
| R24 | Import Excel : doublons, e-mail invalide et classe inconnue sont signalés ; l'aperçu n'écrit rien | lignes rejetées avec motif, base inchangée | `Feature/Admin/ImportComptesTest.php::test_les_doublons_sont_detectes` · `::test_un_email_invalide_est_signale_avec_son_numero_de_ligne` · `::test_l_apercu_n_enregistre_rien` | ✅ |
| R25 | Génération des groupes : idempotente, refusée sans année active | aucun doublon, **422** | `Feature/Admin/GenerationGroupesTest.php::test_la_generation_ne_cree_jamais_de_doublon` · `::test_la_generation_est_bloquee_sans_annee_active` | ✅ |
| R26 | IA sans fragment pertinent : l'IA **n'est pas** appelée | `SANS_SOURCE` | `Feature/IA/QuestionIAApiTest.php::test_sans_fragment_l_ia_n_est_pas_appelee` | ✅ |
| R27 | Gemini en erreur 429 → Groq prend le relais | réponse de Groq | `Feature/IA/ClientIATest.php::test_gemini_429_bascule_vers_groq` | ✅ |
| R28 | Gemini **et** Groq en erreur | **503** | `Feature/IA/QuestionIAApiTest.php::test_ia_indisponible_renvoie_503` | ✅ |
| R29 | Quota de 20 questions par jour | **429** à la 21ᵉ | `Feature/IA/QuestionIAApiTest.php::test_le_quota_du_jour_renvoie_429` · `Feature/IA/AssistantConfigurationApiTest.php::test_20_questions_puis_la_21e_renvoie_429` | ✅ |
| R30 | Assistant inactif dans l'espace | **404** | `Feature/IA/QuestionIAApiTest.php::test_assistant_inactif_ou_absent_renvoie_404` | ✅ |
| R31 | Une annonce notifie les membres ; seul son auteur la supprime | notification reçue ; **403** sinon | `Feature/AnnonceApiTest.php::test_un_gestionnaire_publie_une_annonce_et_notifie_tous_les_membres` · `::test_seul_l_auteur_supprime_son_annonce` | ✅ |
| R32 | Une invitation crée une notification non lue | 1 notification non lue chez l'invité | `Feature/NotificationApiTest.php::test_une_invitation_cree_une_notification_non_lue` (PR SCRUM-121, fusionnée avec `main`) | ✅ |
| R33 | Aucune donnée interne renvoyée à l'API (mot de passe, chemins de fichiers) | pas de `password`, pas de chemin | `Feature/UtilisateurConnecteTest.php::test_aucune_donnee_interne_n_est_exposee` | ✅ |
| R34 | Origines CORS : seulement le front de production et les aperçus Vercel de l'équipe | autres origines bloquées, pas de cookie | `Feature/CorsTest.php::test_les_autres_origines_sont_bloquees` · `::test_pas_de_cookies_on_utilise_des_jetons_bearer` | ✅ |
| R35 | Rattachement automatique à l'activation (classe pour l'étudiant, matières pour l'enseignant) | groupes créés et propriétaire désigné | `Feature/RattachementServiceTest.php::test_rattache_un_etudiant_aux_groupes_de_sa_classe` · `::test_un_enseignant_responsable_devient_proprietaire_des_groupes_de_ses_matieres` | ✅ |
| R36 | Un groupe organisationnel exige une année et une classe, et reste unique | contrainte CHECK + index unique | `Feature/EspacesAdhesionsTest.php::test_un_groupe_organisationnel_exige_une_annee_et_une_classe` · `::test_un_groupe_organisationnel_est_unique_par_annee_classe_matiere` | ✅ |
| R37 | Channels, signalements, modération, demandes d'adhésion, purge automatique | — | — | ⚠️ v1.1 (non livré) |
| R38 | Téléchargement d'une **pièce jointe** de message (`GET /api/fichiers/{id}/telecharger`) | membre de l'espace seulement | — | ⚠️ hors périmètre du 19/11 (SCRUM-137 non livré) |
| R39 | Notification : liste paginée, filtre « non lues », marquage comme lue, « tout lire » | `meta.non_lues` juste, **404** sur la notification d'autrui | `Feature/NotificationApiTest.php` (PR SCRUM-121, fusionnée avec `main`) | ✅ |

> Tests du frontend (Vitest) : `src/router/__tests__/guards.spec.js` (accès aux pages d'administration), `src/stores/__tests__/auth.spec.js` (droits et session), `src/api/__tests__/http.spec.js` (401, 422), `src/composables/__tests__/usePolling.spec.js` (rafraîchissement toutes les 5 s).

> ⚠️ **Point d'attention pour le daily** : la table `notifications` et la classe `NouvelleAnnonce` sont **déjà dans `main`** (SCRUM-123). La PR SCRUM-121 (notifications + API) a été **mise à jour pour s'y adapter** (merge de `main` + suppression de la migration en double + réécriture de `NouvelleAnnonce` sur le modèle `Annonce`, qui respecte désormais `adhesions.notifications_actives`). Les tests sont verts (323 tests). Tant que la PR n'est pas fusionnée, P13 et les notifications d'invitation (P9) ne sont pas testables.

## 4. Procédure de recette croisée

### 4.1 Qui teste quoi

**Personne ne teste sa propre fonctionnalité.** Chaque membre de l'équipe teste les fonctions développées par les autres, avec les comptes de démonstration (au moins deux navigateurs ou deux profils privés, pour voir l'effet de l'autre).

| Testeur | Parcours à tester (développés par d'autres) | Ce qu'on vérifie en priorité |
|---|---|---|
| **Moataz Rabia** | Discussions et pièces jointes (Mouetez) · Assistant IA (Ayoub) · Ressources et chapitres (hayderramma) · Annonces et administration (Mansour) | P5, P6, P11, P12 |
| **Mansour Naili** | Espaces, membres et invitations (Chahredine) · Groupes organisationnels et années (hayderramma) · Discussions (Mouetez) | P8, P9, P17 |
| **Chahredine BACCOUCH** | Inscription, activation, connexion (Moataz) · Discussions et pièces jointes (Mouetez) · Annonces et administration (Mansour) | P1, P2, P3, P10, P17 |
| **MouetezBouajila** | Inscription, activation, connexion (Moataz) · Espaces et membres (Chahredine) · Assistant IA (Ayoub) · Annonces (Mansour) | P1, P3, P8, P12, P10 |
| **Ayoub Boussoukaya** | Inscription et connexion (Moataz) · Espaces, membres et invitations (Chahredine) · Ressources (hayderramma) · Groupes (hayderramma) | P1, P7, P9, P11 |
| **hayderramma** | Espaces, membres et invitations (Chahredine) · Discussions et pièces jointes (Mouetez) · Assistant IA (Ayoub) | P5, P6, P12 |

Chaque ligne du tableau § 2 est cochée au fur et à mesure dans le **commentaire du ticket Jira** (SCRUM-176), avec la date et le nom du testeur.

### 4.2 Comment tester

1. Utiliser la **production** (<https://uniconnect-six-lake.vercel.app>), pas sa machine : c'est la version livrée.
2. Vérifier d'abord que l'API répond : <https://uniconnect-api-qov1.onrender.com/api/health> → `"db": "ok"`.
3. Tester **deux profils en même temps** (deux navigateurs, ou un navigateur + une fenêtre privée) pour les parcours à deux rôles : discussion, invitation, retrait d'un membre.
4. Noter le résultat **exact** : le message affiché, le code HTTP (visible dans l'onglet « Réseau » des outils du navigateur), et une capture d'écran pour les écrans.
5. Ne pas corriger en recette : on note, on crée un ticket Bug (§ 4.3).

### 4.3 Comment créer un ticket Bug

Un bug par anomalie, créé dans le projet Jira **SCRUM**, de type **Bug**, lié à la story concernée (le champ « Se rapporte à »).

| Champ | À écrire |
|---|---|
| Titre | `[Parcours P<n>] <écran> : <symptôme en une phrase>` (ex. `[P5] Espace Révisions BD : le message envoyé n'apparaît pas chez l'autre membre`) |
| Description | **Étapes pour reproduire** (numérotées, depuis l'URL de l'écran), **résultat attendu**, **résultat obtenu**, **compte et rôle utilisés**, **capture d'écran** |
| Priorité | **Highest** si le bug bloque la démonstration (P1–P3, P5, P12) ou si c'est une règle bloquante de l'énoncé (R1–R14) · **High** sinon · **Medium** pour les textes et le confort |
| Label | `recette-18-11` + le parcours (`P5`) ou la règle (`R9`) |
| assignee | la personne qui a développé la fonctionnalité (voir § 4.1 : le bug revient **à celui qui n'a pas testé**) |

Un bug sans étapes pour reproduire et sans capture est renvoyé : on ne peut pas le corriger.

### 4.4 Qui tranche

- Le bug est **reproduit** par un deuxième membre (celui qui a développé la fonction) avant d'être corrigé : sinon il peut s'agir d'une erreur d'utilisation.
- Les corrections passent par une **Pull Request** (branche dédiée, CI verte, 1 relecture) : rien n'est corrigé en direct sur `main`.
- Le **21/11 au daily** : on relit ce plan, on fait le point sur les bugs ouverts et on décide ce qui reste pour la livraison.

## 5. Ce qui n'est pas livré au 19/11 (v1.1)

Ces fonctionnalités sont listées dans [`api.md` § « Routes reportées en v1.1 »](api.md) : **on ne les code pas et on ne les teste pas** avant le 19/11. Elles ont une page de garde dans l'interface, pour que les liens du menu ne cassent pas.

| Fonctionnalité | Ticket | Conséquence sur la recette |
|---|---|---|
| Channels (`/channels`) | v1.1 | page de garde, on ne teste rien |
| Signalements et modération | v1.1 | P15 et P16 ne se testent pas |
| Demandes d'adhésion (`SUR_DEMANDE`) | v1.1 | pas de notification `DEMANDE_ADHESION` à vérifier |
| Téléchargement d'une pièce jointe (`GET /api/fichiers/{id}/telecharger`) | SCRUM-137 | **P6 s'arrête à l'envoi de la pièce jointe** ; pas de téléchargement à tester |
| Purge automatique des messages et fichiers expirés | à faire | le message expiré est **masqué** (R21) mais pas supprimé |
| Mot de passe oublié, recherche, profil, épingles, onglet Fichiers, tableau de bord, historique | v1.1 | pas de test |

## 6. Critère de fin

- Le plan est **relu au daily du 21/11** (c'est le « terminé » du ticket SCRUM-174).
- Chaque ligne du tableau § 2 est cochée ou porte un ticket Bug.
- Le filtre Jira `project = SCRUM AND type = Bug AND priority = Highest AND status != Terminé` est **vide** avant la démonstration du 19/11.
