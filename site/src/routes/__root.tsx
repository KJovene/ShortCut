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
import { MenuCompte } from '@/components/MenuCompte'
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
        {!estAccueil && <SiteHeader className="border-b border-lin" />}
        <main className="flex-1">
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
