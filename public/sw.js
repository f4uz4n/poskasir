const CACHE_NAME = 'poskasir-v3';
const BASE = new URL('./', self.location).pathname.replace(/\/?$/, '/');

const PRECACHE = [
  BASE,
  BASE + 'login',
  BASE + 'manifest.json',
  BASE + 'icons/icon-192.png',
  BASE + 'icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE).catch(() => cache.addAll([
        BASE + 'manifest.json',
      ])))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

async function matchCachedNavigate(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  const url = new URL(request.url);
  const pathname = url.pathname.endsWith('/') ? url.pathname : url.pathname + '/';
  const alt = await caches.match(pathname) || await caches.match(url.pathname.replace(/\/$/, ''));
  if (alt) return alt;

  return caches.match(BASE + 'pos')
    || caches.match(BASE + 'dashboard')
    || caches.match(BASE + 'login')
    || caches.match(BASE);
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  if (url.pathname.includes('/api/') || url.pathname.includes('/transactions')) {
    event.respondWith(
      fetch(request).catch(() => caches.match(request))
    );
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => matchCachedNavigate(request))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request).then((response) => {
        if (!response || response.status !== 200 || response.type === 'opaque') {
          return response;
        }
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        return response;
      }).catch(() => cached);
    })
  );
});
