/*
 * SCRUM-127 : affichage et filtrage des ressources pédagogiques.
 * Les fonctions sont pures pour rester faciles à tester.
 */

export const FILTRES_RESSOURCES = [
  { value: 'TOUT', label: 'Tout' },
  { value: 'PDF', label: 'PDF' },
  { value: 'DOCUMENTS', label: 'Documents' },
  { value: 'LIENS', label: 'Liens' },
]

const MIME_PDF = 'application/pdf'
const MIME_WORD = [
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.oasis.opendocument.text',
]

/** Catégorie utilisée par les filtres de l'écran : PDF, DOCUMENTS ou LIENS. */
export function categorieRessource(ressource) {
  if (!ressource) return 'DOCUMENTS'

  const mime = (ressource.mime ?? '').toLowerCase()
  const aUnFichier = Boolean(mime) || (Boolean(ressource.taille) && !ressource.url)

  if (aUnFichier) {
    return mime === MIME_PDF ? 'PDF' : 'DOCUMENTS'
  }

  if (ressource.url) return 'LIENS'

  // Repli pour les anciennes données sans mime ni url : l'extension du titre.
  const titre = (ressource.titre ?? '').toLowerCase()
  if (titre.endsWith('.pdf')) return 'PDF'

  return 'DOCUMENTS'
}

export function estPdf(ressource) {
  return categorieRessource(ressource) === 'PDF'
}

export function libelleTypeRessource(ressource) {
  if (!ressource) return 'Fichier'

  const mime = (ressource.mime ?? '').toLowerCase()

  if (mime === MIME_PDF) return 'PDF'
  if (MIME_WORD.includes(mime)) return 'Word'
  if (mime) return 'Document'
  if (ressource.url) return 'Lien'

  return (ressource.titre ?? '').toLowerCase().endsWith('.pdf') ? 'PDF' : 'Fichier'
}

export function iconeRessource(ressource) {
  switch (categorieRessource(ressource)) {
    case 'PDF':
      return 'mdi-file-pdf-box'
    case 'LIENS':
      return 'mdi-link-variant'
    default:
      return (ressource?.mime ?? '').toLowerCase().includes('word')
        ? 'mdi-file-word-outline'
        : 'mdi-file-document-outline'
  }
}

export function couleurRessource(ressource) {
  switch (categorieRessource(ressource)) {
    case 'PDF':
      return '#C62828'
    case 'LIENS':
      return '#00796B'
    default:
      return '#1E4FB8'
  }
}

/** « 1,8 Mo », « 120 Ko »… ; « — » quand la taille n'est pas connue (lien). */
export function formaterTaille(octets) {
  if (octets === null || octets === undefined || octets === '') return '—'

  const nombre = Number(octets)
  if (!Number.isFinite(nombre) || nombre < 0) return '—'
  if (nombre < 1024) return `${nombre} o`

  const unites = ['Ko', 'Mo', 'Go', 'To']
  let valeur = nombre / 1024
  let index = 0

  while (valeur >= 1024 && index < unites.length - 1) {
    valeur /= 1024
    index += 1
  }

  // Un chiffre après la virgule sous 10, arrondi à l'unité au-dessus.
  const arrondi = valeur >= 10 ? Math.round(valeur) : Math.round(valeur * 10) / 10

  return `${String(arrondi).replace('.', ',')} ${unites[index]}`
}

const EXTENSIONS = {
  'application/pdf': 'pdf',
  'application/msword': 'doc',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
  'application/vnd.oasis.opendocument.text': 'odt',
  'application/vnd.ms-powerpoint': 'ppt',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
  'application/vnd.ms-excel': 'xls',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
  'text/plain': 'txt',
  'text/csv': 'csv',
  'image/png': 'png',
  'image/jpeg': 'jpg',
}

/** Nom de fichier proposé au téléchargement, dérivé du titre et du type MIME. */
export function nomTelechargement(ressource) {
  const titre = (ressource?.titre ?? 'ressource').trim() || 'ressource'
  const extension = EXTENSIONS[(ressource?.mime ?? '').toLowerCase()] ?? null
  const nom = titre
    .replace(/[\\/:*?"<>|]+/g, '-')
    .replace(/\s+/g, ' ')
    .slice(0, 120)

  if (!extension || nom.toLowerCase().endsWith(`.${extension}`)) return nom

  return `${nom}.${extension}`
}

/**
 * Filtre les ressources d'un chapitre par titre et par type.
 * Le filtre s'applique côté écran, sans nouvel appel réseau.
 */
export function filtrerRessources(ressources, recherche = '', filtre = 'TOUT') {
  const q = (recherche ?? '').trim().toLowerCase()

  return (ressources ?? []).filter((ressource) => {
    const titre = (ressource?.titre ?? '').toLowerCase()
    const titreOk = !q || titre.includes(q)
    const typeOk = !filtre || filtre === 'TOUT' || categorieRessource(ressource) === filtre

    return titreOk && typeOk
  })
}
