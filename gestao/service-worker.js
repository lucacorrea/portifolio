const CACHE_NAME = 'lj-caixa-assets-oo-v9';
const FILES = [
  './assets/css/main.css',
  './assets/js/app.js',
  './assets/js/produto-codigo-barras.js',
  './assets/icons/icon.svg',
  './assets/img/prod-placeholder.svg',
  './assets/img/prod-leite.svg',
  './assets/img/prod-cafe.svg',
  './assets/img/prod-iogurte.svg',
  './assets/img/prod-arroz.svg',
  './assets/img/prod-sabonete.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(FILES)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.map(key => key !== CACHE_NAME ? caches.delete(key) : null))));
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  if (event.request.method !== 'GET') return;
  if (url.pathname.endsWith('.php') || url.pathname.endsWith('/')) return;

  event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request)));
});
