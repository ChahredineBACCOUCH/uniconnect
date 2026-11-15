import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { publierAnnonce, recupererAnnonces } from '@/api/espaces'
import AnnoncesTab from '@/components/espaces/AnnoncesTab.vue'
import vuetify from '@/plugins/vuetify'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/espaces', () => ({
  recupererAnnonces: vi.fn(),
  publierAnnonce: vi.fn(),
}))

const espace = {
  id: 2,
  nom: 'Bases de données',
  type: 'PRIVE',
  etat: 'ACTIF',
  role: 'MEMBRE',
  duree_visibilite_heures: 720,
}

const auteur = { id: 9, prenom: 'Sarra', nom: 'Ben Ali', profil: 'ENSEIGNANT', badge: 'Enseignant' }

function annonce(id, titre, { jours = 0, ...attributs } = {}) {
  return {
    id,
    titre,
    contenu: `Contenu de ${titre}.`,
    auteur,
    notifier_email: false,
    cree_le: new Date(Date.now() - jours * 86_400_000).toISOString(),
    pieces_jointes: [],
    ...attributs,
  }
}

function monter(role = 'MEMBRE', etat = 'ACTIF') {
  return mount(AnnoncesTab, {
    props: { espace: { ...espace, role, etat } },
    global: { plugins: [vuetify] },
  })
}

function bouton(wrapper, texte) {
  return wrapper.findAll('button').find((element) => element.text().includes(texte))
}

/* Le dialogue et la snackbar Vuetify sont téléportés dans `document.body`. */
function element(selecteur) {
  return document.body.querySelector(selecteur)
}

async function saisir(selecteur, valeur) {
  const champ = element(selecteur)
  champ.value = valeur
  champ.dispatchEvent(new Event('input'))
  await flushPromises()
}

async function soumettreFormulaire() {
  element('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
  await flushPromises()
}

describe('AnnoncesTab (SCRUM-124)', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    setActivePinia(createPinia())
    useAuthStore().user = { id: 1, prenom: 'Amine', nom: 'Benali', profil: 'ETUDIANT' }
    vi.clearAllMocks()
  })

  afterEach(() => {
    document.body.innerHTML = ''
    vi.useRealTimers()
  })

  it('un étudiant lit les annonces sans pouvoir publier', async () => {
    recupererAnnonces.mockResolvedValue([
      annonce(2, 'Report de la séance', {
        notifier_email: true,
        pieces_jointes: [
          { id: 1, nom: 'planning.pdf', mime: 'application/pdf', taille_octets: 2048 },
        ],
      }),
      annonce(1, 'Modalités du contrôle', { jours: 4 }),
    ])

    const wrapper = monter()
    await flushPromises()

    expect(recupererAnnonces).toHaveBeenCalledWith(2)
    expect(wrapper.text()).toContain('Report de la séance')
    expect(wrapper.text()).toContain('Sarra Ben Ali')
    expect(wrapper.text()).toContain('planning.pdf')
    expect(wrapper.text()).toContain('PDF')
    expect(wrapper.text()).toContain('Envoyée aussi par e-mail')
    expect(wrapper.text()).toContain('Nouveau')
    expect(bouton(wrapper, 'Nouvelle annonce')).toBeUndefined()
  })

  it('le chip « Nouveau » disparaît après trois jours', async () => {
    recupererAnnonces.mockResolvedValue([
      annonce(2, 'Annonce récente', { jours: 2 }),
      annonce(1, 'Annonce ancienne', { jours: 4 }),
    ])

    const wrapper = monter()
    await flushPromises()

    const cartes = wrapper.findAll('.annonce')

    expect(cartes[0].text()).toContain('Nouveau')
    expect(cartes[0].classes()).toContain('annonce--nouvelle')
    expect(cartes[1].text()).not.toContain('Nouveau')
    expect(cartes[1].classes()).not.toContain('annonce--nouvelle')
  })

  it('un gestionnaire publie une annonce et l’ajoute en tête de liste', async () => {
    recupererAnnonces.mockResolvedValue([annonce(1, 'Ancienne annonce')])
    publierAnnonce.mockResolvedValue(annonce(3, 'Examen de fin de semestre'))

    const wrapper = monter('PROPRIETAIRE')
    await flushPromises()

    await bouton(wrapper, 'Nouvelle annonce').trigger('click')
    await flushPromises()

    expect(element('input[type="file"]').multiple).toBe(true)

    await saisir('input:not([type="file"]):not([type="checkbox"])', 'Examen de fin de semestre')
    await saisir('textarea', 'Mardi prochain, salle B12.')
    await soumettreFormulaire()

    expect(publierAnnonce).toHaveBeenCalledWith(2, {
      titre: 'Examen de fin de semestre',
      contenu: 'Mardi prochain, salle B12.',
      notifier_email: true,
      fichiers: [],
    })

    const titres = wrapper.findAll('.annonce__titre')
    expect(titres[0].text()).toBe('Examen de fin de semestre')
    expect(titres[1].text()).toBe('Ancienne annonce')
    expect(document.body.textContent).toContain('Annonce publiée.')
  })

  it('affiche l’état vide', async () => {
    recupererAnnonces.mockResolvedValue([])

    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Aucune annonce pour le moment.')
  })

  it('affiche une erreur de chargement et permet de réessayer', async () => {
    recupererAnnonces
      .mockRejectedValueOnce(new Error('Serveur indisponible'))
      .mockResolvedValueOnce([annonce(1, 'De retour')])

    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Serveur indisponible')

    await bouton(wrapper, 'Réessayer').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('De retour')
  })

  it('affiche le refus de l’API dans le dialogue', async () => {
    recupererAnnonces.mockResolvedValue([])
    publierAnnonce.mockRejectedValue(
      Object.assign(new Error('422'), {
        response: { status: 422, data: { errors: { titre: ['Le titre est déjà utilisé.'] } } },
      }),
    )

    const wrapper = monter('CO_GESTIONNAIRE')
    await flushPromises()

    await bouton(wrapper, 'Nouvelle annonce').trigger('click')
    await flushPromises()

    await saisir('input:not([type="file"]):not([type="checkbox"])', 'Doublon')
    await saisir('textarea', 'Contenu')
    await soumettreFormulaire()

    expect(document.body.textContent).toContain('Le titre est déjà utilisé.')
  })

  it('cache la publication dans un espace archivé', async () => {
    recupererAnnonces.mockResolvedValue([])

    const wrapper = monter('PROPRIETAIRE', 'ARCHIVE')
    await flushPromises()

    expect(bouton(wrapper, 'Nouvelle annonce')).toBeUndefined()
  })
})
