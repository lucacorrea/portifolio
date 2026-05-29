const CACHE_NAME = 'lj-caixa-pages-premium-v1';
const FILES = [
  './',
  './index.html',
  './manifest.json',
  './assets/css/styles.css',
  './assets/js/data.js',
  './assets/js/app.js',
  './assets/icons/icon.svg',
  './assets/img/prod-placeholder.svg',
  './assets/img/prod-leite.svg',
  './assets/img/prod-cafe.svg',
  './assets/img/prod-iogurte.svg',
  './assets/img/prod-arroz.svg',
  './assets/img/prod-sabonete.svg',
  './pages/nova-venda.html',
  './pages/produtos.html',
  './pages/produto-form.html',
  './pages/relatorios.html',
  './pages/clientes.html',
  './pages/cliente-detalhes.html',
  './pages/historico-vendas.html',
  './pages/venda-detalhes.html',
  './pages/comprovante.html',
  './pages/configuracoes.html'
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
  if (event.request.method !== 'GET') return;
  event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request)));
});
