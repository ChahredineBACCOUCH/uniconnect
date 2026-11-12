/*
 * Simulation de l'API de l'Assistant IA, pour développer l'écran sans le backend.
 * Activée seulement en développement avec VITE_IA_SIMULATION=true dans frontend/.env.
 * À supprimer quand POST /espaces/{id}/ia/questions existe (SCRUM-100 et 102).
 *
 * Elle renvoie exactement le format décrit dans docs/api.md, section 7.
 * Mots-clés pour tester les cas particuliers : « météo » → SANS_SOURCE,
 * « 503 » → IA indisponible, « quota » → quota atteint (429).
 */

const REPONSES = [
  {
    motsCles: ['attribut', '%found', '%rowcount', 'statut'],
    reponse:
      "Les attributs d'un curseur explicite sont :\n- **%FOUND** : vrai si le dernier FETCH a ramené une ligne [1]\n- **%NOTFOUND** : vrai si le dernier FETCH n'a rien ramené [1]\n- **%ISOPEN** : vrai si le curseur est ouvert [1]\n- **%ROWCOUNT** : nombre de lignes déjà traitées [1]",
    sources: [{ n: 1, fichier: 'chap03.pdf', page: 3, ressource_id: 1 }],
  },
  {
    motsCles: ['étape', 'etape', 'utiliser', 'open', 'fetch', 'close'],
    reponse:
      "D'après le cours, un curseur explicite s'utilise en 4 étapes :\n- **Déclaration** dans la partie DECLARE [1]\n- **Ouverture** avec OPEN : allocation mémoire et analyse de la requête [2]\n- **Traitement** ligne par ligne avec FETCH [2]\n- **Fermeture** avec CLOSE pour libérer la mémoire [2]",
    sources: [
      { n: 1, fichier: 'chap03.pdf', page: 1, ressource_id: 1 },
      { n: 2, fichier: 'chap03.pdf', page: 2, ressource_id: 1 },
    ],
  },
]

const REPONSE_PAR_DEFAUT = {
  reponse:
    'Voici ce que dit le cours :\n- Un curseur PL/SQL nomme la zone de travail allouée par Oracle pour une requête [1]\n- Il permet de traiter les lignes une par une [1]\n\nPour aller plus loin, la boucle FOR ouvre, parcourt et ferme le curseur automatiquement [2].',
  sources: [
    { n: 1, fichier: 'chap03.pdf', page: 1, ressource_id: 1 },
    { n: 2, fichier: 'chap03.pdf', page: 4, ressource_id: 1 },
  ],
}

let quotaRestant = 20
let prochainEchange = 1

function attendre(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

// Même forme qu'une erreur Axios, pour que le store la traite comme une vraie réponse d'erreur.
function erreurHttp(status, message) {
  const erreur = new Error(message)
  erreur.response = { status, data: { message } }
  return erreur
}

export async function simulerQuestion(espaceId, { question, conversation_id: conversationId }) {
  await attendre(1200)
  const texte = question.toLowerCase()

  if (texte.includes('503')) throw erreurHttp(503, 'Le service IA ne répond pas.')
  if (texte.includes('quota') || quotaRestant <= 0) {
    throw erreurHttp(429, 'Vous avez atteint vos 20 questions du jour. Revenez demain.')
  }

  quotaRestant -= 1
  const base = {
    conversation_id: conversationId ?? 1,
    echange_id: prochainEchange++,
    quota_restant: quotaRestant,
  }

  if (texte.includes('météo') || texte.includes('meteo')) {
    return { ...base, reponse: '', statut: 'SANS_SOURCE', sources: [] }
  }

  const trouvee = REPONSES.find((r) => r.motsCles.some((mot) => texte.includes(mot)))
  return { ...base, statut: 'OK', ...(trouvee ?? REPONSE_PAR_DEFAUT) }
}
