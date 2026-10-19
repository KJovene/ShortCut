import { describe, expect, it } from 'vitest'
import { ambiancesCompatibles, choisirAmbiance, type Conversation, repondre } from './assistant'
import { demandeVide, extraire } from './demande'

const nouvelle = (): Conversation => ({ demande: demandeVide(), demandes: [] })

describe('assistant simulé', () => {
  it('pose la question du premier critère manquant', () => {
    const r = repondre(nouvelle(), '10 jours au soleil')
    expect(r.complete).toBe(false)
    expect(r.conversation.enAttente).toBe('periode')
  })

  it('propose trois ambiances compatibles avec la température aux dates', () => {
    const d = extraire('en mai, au moins 25 °C', demandeVide())
    const ambiances = ambiancesCompatibles(d)
    expect(ambiances.length).toBeGreaterThan(0)
    expect(ambiances.length).toBeLessThanOrEqual(3)
    expect(ambiances.every((a) => (a.temperature ?? 0) >= 25)).toBe(true)
  })

  it('va jusqu’à la fiche complète, puis lance la proposition', () => {
    let c = nouvelle()
    let r = repondre(c, 'À deux, 10 jours en mai, 4 000 € au total, au moins 25 °C')
    c = r.conversation
    expect(r.messages[0]).toMatchObject({ type: 'ambiances' })

    r = choisirAmbiance(
      c,
      r.messages[0] && 'ambiances' in r.messages[0] ? r.messages[0].ambiances[0].slug : '',
    )
    c = r.conversation
    expect(c.enAttente).toBe('envies')

    r = repondre(c, 'Se poser et la cuisine locale')
    expect(r.conversation.enAttente).toBe('contraintes')

    r = repondre(r.conversation, 'Non, aucune')
    expect(r.complete).toBe(true)
    expect(r.conversation.demande.contraintes).toEqual(['aucune'])
  })

  it('garde une réponse libre quand rien n’est reconnu', () => {
    const c: Conversation = { ...nouvelle(), enAttente: 'periode', demandes: ['periode'] }
    const r = repondre(c, 'pendant les vacances de printemps')
    expect(r.conversation.demande.libres.periode).toBe('pendant les vacances de printemps')
  })
})
