/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import { tanstackRouter } from '@tanstack/router-plugin/vite'
import react from '@vitejs/plugin-react'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    // Le plugin TanStack Router doit passer avant celui de React.
    plugins: [tanstackRouter({ target: 'react', autoCodeSplitting: true }), react(), tailwindcss()],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      // Dans Docker (compose.yaml), les modifications sont détectées par scrutation.
      watch: env.VITE_POLLING ? { usePolling: true, interval: 300 } : undefined,
      // Le site appelle l'API Symfony en relatif (/api/...) : pas de CORS en local.
      // changeOrigin: false garde l'en-tête Host du navigateur (localhost:5173) : sinon Symfony
      // génère des URL absolues vers http://nginx/... (liens EasyAdmin), injoignables depuis l'hôte.
      proxy: Object.fromEntries(
        // /uploads : photos du backoffice et des fixtures.
        // /admin et /bundles : EasyAdmin et ses assets, sur la même origine que le site (même cookie de session).
        ['/api', '/uploads', '/admin', '/bundles'].map((chemin) => [
          chemin,
          { target: env.API_PROXY_TARGET || 'http://127.0.0.1:8000', changeOrigin: false },
        ]),
      ),
    },
    test: {
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.ts'],
    },
  }
})
