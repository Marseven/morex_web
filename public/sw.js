/**
 * Service Worker — MR Money (PWA)
 *
 * Stratégie volontairement prudente pour une app authentifiée (Inertia + CSRF) :
 *  - Navigations (pages HTML) : network-first, fallback cache puis /offline.html.
 *    On ne met JAMAIS en cache les réponses HTML authentifiées (données sensibles),
 *    seul l'app-shell hors-ligne est servi en secours.
 *  - Assets statiques versionnés (/build, /images, /fonts) : cache-first (stale-while-revalidate).
 *  - Tout le reste (API, cross-origin, non-GET) : passthrough réseau, jamais intercepté.
 *
 * Incrémenter CACHE_VERSION à chaque changement de la liste de précache.
 */
const CACHE_VERSION = 'mrmoney-v1';
const PRECACHE = `${CACHE_VERSION}-precache`;
const RUNTIME = `${CACHE_VERSION}-runtime`;

const PRECACHE_URLS = [
  '/offline.html',
  '/manifest.webmanifest',
  '/images/pwa-192.png',
  '/images/pwa-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(PRECACHE).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== PRECACHE && key !== RUNTIME)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

// Permet à la page de forcer l'activation d'un SW en attente
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

function isStaticAsset(url) {
  return (
    url.pathname.startsWith('/build/') ||
    url.pathname.startsWith('/images/') ||
    url.pathname.startsWith('/fonts/') ||
    url.pathname === '/favicon.ico'
  );
}

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Ne toucher qu'aux GET same-origin
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Navigations : network-first avec fallback hors-ligne
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match(request).then((cached) => cached || caches.match('/offline.html'))
      )
    );
    return;
  }

  // Assets statiques : cache-first + revalidation en arrière-plan
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.open(RUNTIME).then((cache) =>
        cache.match(request).then((cached) => {
          const network = fetch(request)
            .then((response) => {
              if (response && response.status === 200) {
                cache.put(request, response.clone());
              }
              return response;
            })
            .catch(() => cached);
          return cached || network;
        })
      )
    );
  }
});
