// VitalSport Service Worker for PWA functionality
// Red primero: siempre se sirve la versión más reciente y la caché solo se usa sin conexión.
// Al cambiar CACHE_NAME se borran las cachés antiguas de los visitantes.
const CACHE_NAME = 'vitalsport-v2';
const urlsToCache = [
  '/',
  '/index.html',
  '/styles.css',
  '/logo_icon.png',
  '/hero-image.png',
  '/team-image.png',
  '/fonts/outfit-latin.woff2'
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - network first, fallback to cache
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Solo GET de la propia web; el chat (/api/) y terceros (Calendly) van directos a la red
  if (event.request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseToCache));
        }
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});
