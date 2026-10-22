import type { QueryClient } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import {
  createRootRouteWithContext,
  HeadContent,
  Outlet,
  useLocation,
} from '@tanstack/react-router'
import { TanStackRouterDevtools } from '@tanstack/react-router-devtools'
import { SiteFooter } from '@/components/layout/SiteFooter'
import { SiteHeader } from '@/components/layout/SiteHeader'
import { NotFound } from '@/components/NotFound'
import { ChatFenetre, ChatLanceur } from '@/features/chat/components/ChatFenetre'
import { ChatProvider } from '@/features/chat/ChatProvider'

export interface RouterContext {
  queryClient: QueryClient
}

export const Route = createRootRouteWithContext<RouterContext>()({
  head: () => ({
    meta: [
      { title: 'Agence de voyage sur mesure' },
      {
        name: 'description',
        content:
          "Décrivez vos envies à notre assistant : il vous propose un voyage complet et chiffré, qu'un conseiller confirme ensuite.",
      },
    ],
  }),
  component: RootLayout,
  notFoundComponent: NotFound,
})

function RootLayout() {
  // L'accueil place l'en-tête dans sa section plein écran avec la carte.
  const estAccueil = useLocation({ select: (l) => l.pathname === '/' })

  return (
    <ChatProvider>
      <HeadContent />
      <div className="flex min-h-screen flex-col overflow-x-clip">
        {/* Premier élément au clavier : passe l'en-tête et le menu. */}
        <a
          href="#contenu"
          className="sr-only z-50 rounded-full bg-encre px-5 py-3 font-bold text-creme focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
          Aller au contenu
        </a>
        {!estAccueil && <SiteHeader className="border-b border-lin" />}
        <main id="contenu" tabIndex={-1} className="flex-1 focus:outline-none">
          <Outlet />
        </main>
        <SiteFooter />
      </div>
      <ChatLanceur />
      <ChatFenetre />
      {import.meta.env.DEV && (
        <>
          <ReactQueryDevtools buttonPosition="bottom-left" />
          <TanStackRouterDevtools position="top-right" />
        </>
      )}
    </ChatProvider>
  )
}
