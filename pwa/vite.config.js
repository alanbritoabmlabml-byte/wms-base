import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import { viteSingleFile } from 'vite-plugin-singlefile'

// `single` produce un único index.html autocontenido (para pegarlo en cualquier
// hosting o abrirlo desde el colector sin servidor). `demo` deja la app en modo
// demo (backend simulado en IndexedDB). Por defecto se construye la PWA normal.
export default defineConfig(({ mode }) => ({
  base: process.env.VITE_BASE || '/',
  plugins: [
    vue(),
    mode !== 'single' &&
      VitePWA({
        registerType: 'autoUpdate',
        includeAssets: ['favicon.svg'],
        manifest: {
          name: 'WMS Base — Colector',
          short_name: 'WMS',
          description: 'Terminal de almacén para colectores Zebra',
          lang: 'es',
          start_url: '.',
          scope: '.',
          display: 'standalone',
          orientation: 'portrait',
          background_color: '#0E141A',
          theme_color: '#0E141A',
          icons: [
            { src: 'icon-192.png', sizes: '192x192', type: 'image/png' },
            { src: 'icon-512.png', sizes: '512x512', type: 'image/png' },
            { src: 'icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
          ],
        },
        workbox: {
          globPatterns: ['**/*.{js,css,html,svg,png,woff2}'],
          // El shell se sirve desde caché: la app abre aunque no haya red en la nave.
          navigateFallback: 'index.html',
          runtimeCaching: [
            {
              urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
              handler: 'NetworkOnly', // los datos van por la cola propia, no por Workbox
            },
          ],
        },
      }),
    mode === 'single' && viteSingleFile({ removeViteModuleLoader: true }),
  ].filter(Boolean),
  define: {
    __APP_VERSION__: JSON.stringify(process.env.npm_package_version || '0.1.0'),
  },
  build: {
    target: 'es2019', // los colectores Zebra viejos traen Chrome/WebView antiguo
    cssCodeSplit: mode !== 'single',
    assetsInlineLimit: mode === 'single' ? 100000000 : 4096,
  },
  server: { host: true, port: 5173 },
}))
