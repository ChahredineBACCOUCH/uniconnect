# Guide utilisateur UniConnect

UniConnect est la messagerie de votre université. Vous y retrouvez automatiquement les espaces de votre classe et de vos matières, et un **Assistant IA** qui répond à vos questions à partir des supports de vos enseignants.

Il fonctionne sur ordinateur et sur téléphone. Sur téléphone, le menu est en bas de l'écran.

---

## 1. Créer et activer son compte (tout le monde)

1. Ouvrez UniConnect, puis cliquez sur **Créer mon compte**.
2. Saisissez votre **matricule**, votre nom, votre prénom et votre **adresse e-mail de l'université** (`…@univ.tn`). Cochez la charte, puis validez.
   - Message « Cette adresse ne figure pas sur la liste des comptes autorisés » : vérifiez l'adresse, sinon contactez la scolarité.
3. Ouvrez l'e-mail reçu et cliquez sur le lien d'activation (**valable 48 heures**). S'il a expiré, cliquez sur **Renvoyer l'e-mail d'activation**.
4. Choisissez votre mot de passe : 8 caractères minimum, avec une majuscule, une minuscule et un chiffre.
5. C'est fait : vous êtes connecté et vous avez rejoint automatiquement vos espaces.

**Connexion** : adresse e-mail + mot de passe. Après **5 erreurs**, le compte est bloqué **15 minutes**.

---

## 2. Étudiant

### Retrouver ses espaces
L'**accueil** affiche vos espaces : votre classe, chacune de vos matières et vos groupes privés. Le chiffre bleu indique le nombre de messages non lus.

### Discuter
1. Ouvrez un espace : l'onglet **Discussion** s'affiche.
2. Écrivez en bas, puis cliquez sur **Envoyer** (ou appuyez sur Entrée).
3. Pour joindre un fichier, cliquez sur 📎. Les fichiers sont visibles **uniquement par les membres** de l'espace.

### Annonces et ressources
- **Annonces** : informations officielles de l'enseignant (vous recevez une notification).
- **Ressources** : supports de cours classés par chapitre, à consulter ou télécharger.

### Poser une question à l'Assistant IA
1. Dans l'espace d'une matière, ouvrez l'onglet **Assistant IA** (visible si l'enseignant l'a activé).
2. Posez votre question en français, par exemple : « Quelle est la différence entre la 2FN et la 3FN ? »
3. La réponse indique ses **sources** (« [1] Chapitre 3 – Normalisation.pdf · p. 14 ») : cliquez dessus pour ouvrir le document.
4. Donnez votre avis avec 👍 ou 👎 : cela aide à améliorer l'assistant.

À savoir :
- l'assistant répond **uniquement à partir des documents du cours**. Si l'information n'y est pas, il le dit ;
- **20 questions par jour** au maximum ;
- vos questions sont **privées** : ni l'enseignant ni les autres étudiants ne les voient ;
- l'IA peut se tromper : **vérifiez toujours dans les sources**.

### Groupes privés
1. Cliquez sur **Créer un groupe privé**, donnez-lui un nom, puis invitez des personnes.
2. Les invités reçoivent une notification et choisissent **Accepter** ou **Refuser**.
3. Vous pouvez quitter un groupe privé, mais pas le groupe de votre classe.

---

## 3. Enseignant

Vous avez accès à tout ce que fait un étudiant, et en plus :

| Action | Où | Comment |
|---|---|---|
| Publier une annonce | Onglet **Annonces** | Titre + texte → **Publier** : les membres reçoivent une notification |
| Partager un support de cours | Onglet **Ressources** | **Nouveau chapitre**, puis **Ajouter une ressource** (PDF ou lien). Les PDF sont indexés pour l'IA : la puce « Indexée pour l'IA » apparaît. |
| Activer l'Assistant IA | Onglet **Paramètres** | Interrupteur « Assistant IA ». Vous pouvez ajouter une consigne, par exemple « Donne des exemples SQL ». |
| Gérer les membres | Onglet **Membres** | Inviter, nommer un co-gestionnaire, retirer un membre (l'accès est coupé immédiatement) |

Conseil : l'assistant n'est utile que si vos PDF sont **en texte** (pas des scans d'images) et classés par chapitre.

---

## 4. Membre de l'administration

Menu **ADMINISTRATION** (visible seulement avec les droits correspondants) :

| Écran | À quoi il sert |
|---|---|
| **Années universitaires** | Créer l'année suivante, puis l'**activer** au bon moment (une seule année active à la fois) |
| **Référentiels** | Départements, filières, niveaux, classes et matières. Un élément utilisé ne peut pas être supprimé. |
| **Comptes autorisés** | Liste des personnes qui peuvent créer un compte. **Importer un fichier CSV** : un aperçu montre les erreurs ligne par ligne avant d'importer. |
| **Groupes organisationnels** | **Générer les groupes** de l'année active : un groupe par classe et un par matière. On peut relancer sans risque, sans créer de doublons. |

Format du fichier CSV : `matricule;nom;prenom;email;profil;classe_code` (UTF-8, séparateur `;`, `profil` = ETUDIANT / ENSEIGNANT / ADMINISTRATION).

---

## 5. Questions fréquentes

| Question | Réponse |
|---|---|
| Je ne reçois pas l'e-mail d'activation | Vérifiez vos spams, puis cliquez sur « Renvoyer l'e-mail d'activation ». |
| J'ai oublié mon mot de passe | La réinitialisation en ligne arrive dans une prochaine version. En attendant, contactez la scolarité. |
| Je ne vois pas l'onglet Assistant IA | L'enseignant ne l'a pas encore activé pour cette matière. |
| « Vous avez atteint vos 20 questions du jour » | Le quota se remet à zéro le lendemain. |
| « L'assistant est momentanément indisponible » | Le service d'IA est saturé : réessayez dans quelques minutes. |
| Un message ou un fichier a disparu | Il a peut-être été supprimé par son auteur, ou vous n'êtes plus membre de l'espace. |
