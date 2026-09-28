/**
 * Web app manifest of Rocket Stock (installable on Android and iPhone), served by /manifest.webmanifest.
 * Colours: amber-600 (primary of app.config.ts), white background.
 */
export function pwaManifest(startUrl = '/courses') {
  return {
    id: '/',
    name: 'Rocket Stock',
    short_name: 'Stock',
    description: 'Le stock de tes lieux : consommables, linge, équipements, courses et bilan.',
    lang: 'fr',
    dir: 'ltr',
    start_url: startUrl,
    scope: '/',
    display: 'standalone',
    orientation: 'portrait',
    theme_color: '#d97706',
    background_color: '#ffffff',
    categories: ['productivity', 'business'],
    icons: [
      { src: '/icons/stock-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
      { src: '/icons/stock-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
      { src: '/icons/stock-maskable-192.png', sizes: '192x192', type: 'image/png', purpose: 'maskable' },
      { src: '/icons/stock-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
      { src: '/icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
    ],
    shortcuts: [
      { name: 'Liste de courses', short_name: 'Courses', url: '/courses', icons: [{ src: '/icons/stock-192.png', sizes: '192x192' }] },
      { name: 'Panier', short_name: 'Panier', url: '/courses/panier', icons: [{ src: '/icons/stock-192.png', sizes: '192x192' }] },
    ],
  }
}

export function sendManifest(event: Parameters<typeof setResponseHeader>[0], startUrl?: string) {
  setResponseHeader(event, 'Content-Type', 'application/manifest+json; charset=utf-8')
  setResponseHeader(event, 'Cache-Control', 'no-cache')
  return pwaManifest(startUrl)
}
