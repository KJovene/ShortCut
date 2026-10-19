import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// jsdom n'implémente pas le défilement : le chatbot fait défiler la conversation et la page.
Element.prototype.scrollIntoView = () => {}
Element.prototype.scrollTo = () => {}

afterEach(() => {
  cleanup()
})
