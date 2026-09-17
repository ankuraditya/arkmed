import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'prompt',
      includeAssets: ['arkmed-mark.svg', 'logo.png'],
      manifest: {
        id: '/',
        lang: 'en-IN',
        name: 'ARK med Pharmacy & Clinic',
        short_name: 'ARK med',
        description: 'Medicine ordering, prescriptions, consultations and health checkups.',
        theme_color: '#083a70',
        background_color: '#f7fbff',
        display: 'standalone',
        start_url: '/',
        scope: '/',
        orientation: 'portrait-primary',
        categories: ['medical', 'health', 'shopping'],
        shortcuts: [
          { name: 'Browse medicines', short_name: 'Medicines', url: '/medicines' },
          { name: 'Upload prescription', short_name: 'Prescription', url: '/upload-prescription' },
          { name: 'Track an order', short_name: 'Track order', url: '/track-order' }
        ],
        icons: [
          { src: '/arkmed-mark.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any maskable' }
        ]
      },
      workbox: {
        cleanupOutdatedCaches: true,
        navigationPreload: true,
        navigateFallback: '/index.html',
        runtimeCaching: [
          {
            urlPattern: ({ request }) => request.destination === 'image',
            handler: 'CacheFirst',
            options: {
              cacheName: 'arkmed-public-images',
              expiration: { maxEntries: 40, maxAgeSeconds: 60 * 60 * 24 * 14 }
            }
          }
        ]
      }
    })
  ]
})
