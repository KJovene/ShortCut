import { describe, expect, it } from 'vitest'
import { DESTINATIONS_DE_DEMO } from '@/test/destinations'
import { destinationDuLieu, versDestination } from './catalogue'

const destinations = DESTINATIONS_DE_DEMO.map(versDestination)
const slugDe = (lieu: string | null) => destinationDuLieu(lieu, destinations)?.slug

describe('destination du lieu saisi par le client', () => {
  it('reconnaît la ville, le nom ou le pays, sans tenir compte des accents ni de la casse', () => {
    expect(slugDe('Reykjavik')).toBe('islande')
    expect(slugDe('santorin')).toBe('santorin')
    expect(slugDe('Maroc')).toBe('marrakech')
    expect(slugDe('Grece')).toBe('santorin')
    expect(slugDe('Canaries')).toBe('tenerife')
  })

  it('ne devine rien pour un lieu absent du catalogue', () => {
    expect(slugDe('Patagonie')).toBeUndefined()
    expect(slugDe('')).toBeUndefined()
    expect(slugDe(null)).toBeUndefined()
  })
})
