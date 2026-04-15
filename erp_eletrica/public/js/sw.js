/**
 * ERP Elétrica — Service Worker (Modo Híbrido Offline/Online)
 * 
 * CONSERVADOR: Só cacheia assets estáticos e as páginas CRÍTICAS (PDV, Pré-Venda).
 * NÃO intercepta outras páginas PHP para evitar telas pretas/quebradas.
 */

const CACHE_VERSION = 'erp-eletrica-v2';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const PAGES_CACHE = `${CACHE_VERSION}-pages`;

// Páginas que DEVEM funcionar offline
const CRITICAL_PAGES = [
    'vendas.php',
    'pre_vendas.php',
];

// Assets estáticos locais para pré-cachear
const PRECACHE_ASSETS = [
    'public/css/corporate.css',
    'style.css',
    'public/js/corporate.js',
    'public/js/offline-bridge.js',
    'script.js',
];

// ===== HELPERS =====

/** Verifica se é um asset estático LOCAL (CSS, JS, imagens, fontes) */
function isLocalStaticAsset(url) {
    const u = new URL(url);
    // Apenas assets do nosso domínio
    if (u.origin !== self.location.origin) return false;
    return /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff2?|ttf|eot)(\?.*)?$/i.test(u.pathname);
}

/** Verifica se é um recurso CDN (Bootstrap, Font Awesome, Google Fonts) */
function isCDNAsset(url) {
    return url.includes('cdn.jsdelivr.net') ||
           url.includes('cdnjs.cloudflare.com') ||
           url.includes('fonts.googleapis.com') ||
           url.includes('fonts.gstatic.com');
}

/** Verifica se é uma das páginas críticas que queremos cachear */
function isCriticalPage(url) {
    const u = new URL(url);
    const path = u.pathname;
    // Só intercepta se for uma das páginas críticas SEM action (página principal)
    // Requests com action= são API calls tratados pelo offline-bridge.js
    if (u.searchParams.has('action')) return false;
    return CRITICAL_PAGES.some(page => path.endsWith(page));
}

// ===== INSTALL =====
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando v2...');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                return Promise.allSettled(
                    PRECACHE_ASSETS.map(url =>
                        cache.add(url).catch(err =>
                            console.warn(`[SW] Falha ao pré-cachear: ${url}`, err)
                        )
                    )
                );
            })
            .then(() => self.skipWaiting())
    );
});

// ===== ACTIVATE =====
self.addEventListener('activate', (event) => {
    console.log('[SW] Ativando v2...');
    event.waitUntil(
        caches.keys()
            .then(names => Promise.all(
                names
                    .filter(name => name.startsWith('erp-eletrica-') && !name.startsWith(CACHE_VERSION))
                    .map(name => caches.delete(name))
            ))
            .then(() => self.clients.claim())
    );
});

// ===== FETCH =====
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = request.url;

    // REGRA 1: NUNCA interceptar POST, PUT, DELETE
    if (request.method !== 'GET') return;

    // REGRA 2: NUNCA interceptar login, logout, api_sync (heartbeat)
    if (url.includes('login.php') || url.includes('logout.php') || url.includes('api_sync.php')) return;

    // REGRA 3: Assets estáticos LOCAIS → Cache First
    if (isLocalStaticAsset(url)) {
        event.respondWith(cacheFirstSafe(request, STATIC_CACHE));
        return;
    }

    // REGRA 4: CDN Assets → Cache First
    if (isCDNAsset(url)) {
        event.respondWith(cacheFirstSafe(request, STATIC_CACHE));
        return;
    }

    // REGRA 5: Páginas críticas (PDV, Pré-Venda) → Network First, Cache Fallback
    if (isCriticalPage(url)) {
        event.respondWith(networkFirstSafe(request, PAGES_CACHE));
        return;
    }

    // QUALQUER OUTRA COISA: NÃO INTERCEPTAR (comportamento padrão do browser)
    // Isso evita telas pretas em páginas que não são críticas
});

// ===== ESTRATÉGIAS SEGURAS =====

/**
 * Cache First com fallback seguro.
 * Se o cache falhar E a rede falhar, retorna a resposta de rede crua
 * em vez de um erro customizado.
 */
async function cacheFirstSafe(request, cacheName) {
    try {
        const cache = await caches.open(cacheName);
        const cached = await cache.match(request);

        if (cached) {
            // Background update (stale-while-revalidate)
            fetch(request).then(res => {
                if (res && res.ok) cache.put(request, res);
            }).catch(() => {});
            return cached;
        }

        // Não está no cache → busca na rede
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        // Fallback: tenta cache antigo
        try {
            const cache = await caches.open(cacheName);
            const cached = await cache.match(request);
            if (cached) return cached;
        } catch (e) {}

        // Último recurso: deixa o browser lidar
        return fetch(request);
    }
}

/**
 * Network First com cache fallback SEGURO.
 * Timeout generoso (10s). Se tudo falhar, tenta cache.
 * NUNCA retorna uma resposta vazia/error — prefere deixar o browser lidar.
 */
async function networkFirstSafe(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        // Timeout generoso para servidores lentos (Hostinger)
        const response = await fetchWithTimeout(request, 10000);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        // Rede falhou → tenta cache
        console.log(`[SW] Rede falhou para ${request.url}, tentando cache...`);
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }

        // Sem cache → tenta a rede novamente sem timeout (última chance)
        try {
            return await fetch(request);
        } catch (e) {
            // Retorna uma página de erro simples ao invés de uma tela preta
            return new Response(
                `<html><body style="font-family:Inter,sans-serif;text-align:center;padding:50px">
                    <h2>⚡ Sem Conexão</h2>
                    <p>Esta página não está disponível offline.</p>
                    <p><a href="vendas.php">Ir para o PDV (disponível offline)</a></p>
                </body></html>`,
                { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            );
        }
    }
}

function fetchWithTimeout(request, timeout) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Timeout')), timeout);
        fetch(request)
            .then(res => { clearTimeout(timer); resolve(res); })
            .catch(err => { clearTimeout(timer); reject(err); });
    });
}

// ===== MENSAGENS =====
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
    if (event.data?.type === 'CLEAR_API_CACHE') {
        // Nada a fazer — API cache não é mais gerenciado pelo SW
        console.log('[SW] Cache refresh solicitado');
    }
});
