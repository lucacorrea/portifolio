const CACHE_NAME = 'gestao-assets-v12';
const STATIC_FILES = [
  './assets/css/main.css',
  './assets/js/app.js',
  './assets/js/pwa-install.js',
  './assets/js/produto-codigo-barras.js',
  './assets/img/prod-placeholder.svg',
  './assets/img/prod-leite.svg',
  './assets/img/prod-cafe.svg',
  './assets/img/prod-iogurte.svg',
  './assets/img/prod-arroz.svg',
  './assets/img/prod-sabonete.svg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_FILES)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.map((key) => (key !== CACHE_NAME ? caches.delete(key) : null))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) return;
  if (url.pathname.endsWith('.php') || url.pathname.endsWith('/') || url.pathname.endsWith('/manifest.php')) return;

  if (url.pathname.includes('/uploads/')) {
    event.respondWith(cacheByFullUrl(request));
    return;
  }

  if (url.pathname.endsWith('.js') || url.pathname.endsWith('.css')) {
    event.respondWith(networkFirst(request));
    return;
  }

  event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
});

async function networkFirst(request) {
  try {
    const response = await fetch(request);
    const cache = await caches.open(CACHE_NAME);
    await cache.put(request, response.clone());
    return response;
  } catch (error) {
    const cached = await caches.match(request);
    if (cached) return cached;
    throw error;
  }
}

async function cacheByFullUrl(request) {
  const cache = await caches.open(CACHE_NAME);
  const cached = await cache.match(request);

  if (cached) return cached;

  const response = await fetch(request);
  await cache.put(request, response.clone());
  return response;
}
