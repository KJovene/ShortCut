import '@testing-library/jest-dom/vitest'
import { cleanup, configure } from '@testing-library/react'
import { afterEach } from 'vitest'

// L'accueil (carte du monde, chatbot) met parfois plus d'une seconde à s'afficher au premier lancement,
// quand Vite compile tout à froid : findBy et waitFor attendent jusqu'à 3 s au lieu d'1 s.
configure({ asyncUtilTimeout: 3000 })

// jsdom n'implémente pas le défilement : le chatbot fait défiler la conversation et la page.
Element.prototype.scrollIntoView = () => {}
Element.prototype.scrollTo = () => {}

afterEach(() => {
  cleanup()
})
