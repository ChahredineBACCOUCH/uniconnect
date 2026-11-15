/*
 * SCRUM-139 : règles des fichiers joints aux messages.
 *
 * Miroir de `backend/config/uniconnect.php` (table `regles_plateforme`) : la
 * validation côté navigateur évite d'envoyer un fichier que l'API refusera,
 * mais la validation qui fait foi reste celle du serveur.
 */

export const MAX_FICHIERS = 5
export const TAILLE_MAX_MO = 25
export const TAILLE_MAX_OCTETS = TAILLE_MAX_MO * 1024 * 1024

export const FORMATS_AUTORISES = [
  'pdf',
  'jpg',
  'jpeg',
  'png',
  'gif',
  'webp',
  'doc',
  'docx',
  'xls',
  'xlsx',
  'ppt',
  'pptx',
  'zip',
]

/** Valeur de l'attribut `accept` de l'input file. */
export const ACCEPT_FICHIERS = FORMATS_AUTORISES.map((extension) => `.${extension}`).join(',')

/** Extension en minuscules, sans le point ; chaîne vide si le nom n'en a pas. */
export function extensionFichier(nom) {
  const texte = (nom ?? '').trim()
  const point = texte.lastIndexOf('.')

  if (point <= 0 || point === texte.length - 1) return ''

  return texte.slice(point + 1).toLowerCase()
}

export function estFormatAutorise(fichier) {
  return FORMATS_AUTORISES.includes(extensionFichier(fichier?.name))
}

/**
 * Filtre une nouvelle sélection avant l'envoi.
 *
 * @param {File[]} nouveaux Fichiers choisis ou déposés.
 * @param {File[]} dejaJoints Fichiers déjà ajoutés au message.
 * @returns {{ acceptes: File[], erreur: string|null }}
 */
export function verifierFichiers(nouveaux, dejaJoints = []) {
  const acceptes = []
  const erreurs = []
  let total = dejaJoints.length

  for (const fichier of nouveaux ?? []) {
    if (total >= MAX_FICHIERS) {
      erreurs.push(`Vous ne pouvez joindre que ${MAX_FICHIERS} fichiers au maximum.`)
      break
    }

    if (Number(fichier.size) > TAILLE_MAX_OCTETS) {
      erreurs.push(`« ${fichier.name} » dépasse la taille maximale de ${TAILLE_MAX_MO} Mo.`)
      continue
    }

    if (!estFormatAutorise(fichier)) {
      erreurs.push(`Le format de « ${fichier.name} » n'est pas autorisé.`)
      continue
    }

    acceptes.push(fichier)
    total += 1
  }

  return {
    acceptes,
    erreur: erreurs.length > 0 ? erreurs.join(' ') : null,
  }
}
