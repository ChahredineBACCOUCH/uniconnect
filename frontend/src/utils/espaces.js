/*
 * Fonctions d'affichage et de calcul partagées par l'écran d'un espace.
 * Elles sont pures pour rester faciles à tester.
 */

export const ROLES_GESTIONNAIRES = ['PROPRIETAIRE', 'CO_GESTIONNAIRE']

export const LIBELLES_TYPE = {
  ORGANISATIONNEL: 'Groupe organisationnel',
  PRIVE: 'Groupe privé',
  CHANNEL: 'Channel',
}

const ICONES_TYPE = {
  ORGANISATIONNEL: 'mdi-account-group-outline',
  PRIVE: 'mdi-lock-outline',
  CHANNEL: 'mdi-pound',
}

const COULEURS_AVATAR = ['#1E4FB8', '#00796B', '#01579B', '#8A4100', '#6A1B9A', '#B45309']

export function libelleType(type) {
  return LIBELLES_TYPE[type] ?? 'Espace'
}

export function iconeEspace(type) {
  return ICONES_TYPE[type] ?? 'mdi-account-group-outline'
}

export function estGestionnaire(role) {
  return ROLES_GESTIONNAIRES.includes(role)
}

const LIBELLES_ROLE = {
  PROPRIETAIRE: 'Propriétaire',
  CO_GESTIONNAIRE: 'Co-gestionnaire',
  MEMBRE: 'Membre',
}

export function libelleRole(role) {
  return LIBELLES_ROLE[role] ?? 'Membre'
}

/** Un membre simple ne peut pas publier dans un espace en mode annonce. */
export function peutPublier(espace) {
  if (!espace || espace.etat === 'ARCHIVE') return false
  return !espace.mode_annonce || estGestionnaire(espace.role)
}

/** Libellé de la durée de visibilité, `null` signifiant « illimitée ». */
export function libelleDuree(heures) {
  if (heures === null || heures === undefined) return 'durée illimitée'

  if (heures % 24 === 0) {
    const jours = heures / 24
    return jours === 1 ? '24 heures' : `${jours} jours`
  }

  return heures === 1 ? '1 heure' : `${heures} heures`
}

export function libelleVisibilite(heures) {
  if (heures === null || heures === undefined) return 'Messages sans expiration'
  return `Messages visibles ${libelleDuree(heures)}`
}

/**
 * Durées plus courtes proposées dans le composer. La durée choisie doit être
 * strictement inférieure à celle de l'espace (règle de StoreMessageRequest).
 */
export function optionsDuree(espace) {
  if (!espace?.duree_courte_autorisee) return []

  const candidates = [1, 6, 12, 24, 168, 720]
  const maximum = espace.duree_visibilite_heures

  const options = candidates
    .filter((heures) => maximum === null || maximum === undefined || heures < maximum)
    .map((heures) => ({ title: libelleDuree(heures), value: heures }))

  return [{ title: `Durée de l’espace (${libelleDuree(maximum)})`, value: null }, ...options]
}

const formateurHeure = new Intl.DateTimeFormat('fr-FR', {
  hour: '2-digit',
  minute: '2-digit',
})

const formateurDateCourte = new Intl.DateTimeFormat('fr-FR', {
  day: '2-digit',
  month: '2-digit',
})

const formateurJourSemaine = new Intl.DateTimeFormat('fr-FR', { weekday: 'long' })

const formateurDateLongue = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

function capitaliser(texte) {
  return texte.charAt(0).toUpperCase() + texte.slice(1)
}

function jourLocal(iso) {
  const date = new Date(iso)
  return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`
}

export function formaterHeure(iso) {
  if (!iso) return ''
  return formateurHeure.format(new Date(iso))
}

export function formaterJour(iso, maintenant = new Date()) {
  if (!iso) return ''

  const date = new Date(iso)
  const jour = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  const aujourdhui = new Date(maintenant.getFullYear(), maintenant.getMonth(), maintenant.getDate())
  const ecartJours = Math.round((aujourdhui.getTime() - jour.getTime()) / 86_400_000)

  if (ecartJours === 0) return "Aujourd'hui"
  if (ecartJours === 1) return 'Hier'
  if (ecartJours > 1 && ecartJours < 7) return capitaliser(formateurJourSemaine.format(date))

  return capitaliser(formateurDateLongue.format(date))
}

/** Insère un séparateur « Aujourd'hui », « Hier »… avant chaque nouveau jour. */
export function grouperMessagesParJour(messages, maintenant = new Date()) {
  const entrees = []
  let dernierJour = null

  for (const message of messages) {
    const jour = jourLocal(message.cree_le)

    if (jour !== dernierJour) {
      entrees.push({
        type: 'jour',
        cle: `jour-${jour}`,
        libelle: formaterJour(message.cree_le, maintenant),
      })
      dernierJour = jour
    }

    entrees.push({ type: 'message', cle: `message-${message.id}`, message })
  }

  return entrees
}

/**
 * Ajoute les nouveaux messages et met à jour ceux déjà affichés (édition).
 * Le tri par identifiant garde l'ordre chronologique de l'API.
 */
export function fusionnerMessages(existants, nouveaux) {
  const parId = new Map(existants.map((message) => [message.id, message]))

  for (const message of nouveaux) {
    parId.set(message.id, message)
  }

  return [...parId.values()].sort((a, b) => a.id - b.id)
}

export function formaterExpiration(iso, maintenant = new Date()) {
  if (!iso) return null

  const date = new Date(iso)
  const memeJour = jourLocal(iso) === jourLocal(maintenant.toISOString())

  if (memeJour) {
    return `Expire aujourd'hui à ${formateurHeure.format(date)}`
  }

  return `Expire le ${formateurDateCourte.format(date)} à ${formateurHeure.format(date)}`
}

export function initialesAuteur(auteur) {
  if (!auteur) return '?'

  return `${auteur.prenom?.[0] ?? ''}${auteur.nom?.[0] ?? ''}`.toUpperCase() || '?'
}

export function couleurAuteur(auteur) {
  const id = auteur?.id ?? 0
  return COULEURS_AVATAR[id % COULEURS_AVATAR.length]
}

export function nomAuteur(auteur, utilisateurId) {
  if (!auteur) return 'Membre supprimé'
  if (auteur.id === utilisateurId) return 'Vous'
  return `${auteur.prenom ?? ''} ${auteur.nom ?? ''}`.trim() || 'Membre'
}

/** Badge affiché à côté du nom (enseignant, administration). */
export function badgeAuteur(auteur) {
  if (!auteur || !['ENSEIGNANT', 'ADMINISTRATION'].includes(auteur.profil)) return null
  return auteur.badge
}

export function extrait(texte, longueur = 90) {
  if (!texte) return ''
  return texte.length > longueur ? `${texte.slice(0, longueur)}…` : texte
}

/** Message lisible pour une erreur Axios ou de validation. */
export function messageErreur(error, defaut = 'Une erreur est survenue. Réessayez.') {
  if (!error) return defaut

  const erreurs = error.errors ?? error.response?.data?.errors
  if (erreurs) {
    const premier = Object.values(erreurs).flat()[0]
    if (premier) return premier
  }

  // Le message français de l'API d'abord : `error.message` d'Axios vaut sinon
  // « Request failed with status code 403 », illisible pour l'utilisateur.
  return error.response?.data?.message ?? error.message ?? defaut
}

const LIBELLES_PROFIL = {
  ETUDIANT: 'Étudiant',
  ENSEIGNANT: 'Enseignant',
  ADMINISTRATION: 'Administration',
}

export function libelleProfil(profil) {
  return LIBELLES_PROFIL[profil] ?? 'Membre'
}

const formateurDate = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

/** Date courte « 10 nov. 2026 » pour les listes de membres et d'invitations. */
export function formaterDate(iso) {
  if (!iso) return ''
  return formateurDate.format(new Date(iso))
}

/** Durées de visibilité proposées à la création d'un groupe privé (config/uniconnect.php). */
export const DUREES_CREATION = [
  { valeur: 168, libelle: '7 jours', detail: 'Par défaut' },
  { valeur: 24, libelle: '24 heures', detail: null },
  { valeur: 720, libelle: '30 jours', detail: null },
  { valeur: null, libelle: 'Illimitée', detail: 'Les messages ne disparaissent jamais' },
]

/**
 * Actions possibles sur un membre, selon le rôle de l'utilisateur connecté
 * (règles de MembreController) : le propriétaire gère tout, un co-gestionnaire
 * ne peut que retirer de simples membres, personne n'agit sur le propriétaire
 * ni sur soi-même.
 */
export function actionsMembre(monRole, membre, monId) {
  if (!membre || membre.user.id === monId || membre.role === 'PROPRIETAIRE') return []

  if (monRole === 'PROPRIETAIRE') {
    return [membre.role === 'MEMBRE' ? 'nommer' : 'retrograder', 'transferer', 'retirer']
  }

  if (monRole === 'CO_GESTIONNAIRE' && membre.role === 'MEMBRE') {
    return ['retirer']
  }

  return []
}
