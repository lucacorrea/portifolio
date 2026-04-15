/**
 * ERP Elétrica — Service Worker (Modo Híbrido Offline/Online)
 * 
 * Estratégia:
 *  - Static Assets (CSS, JS, Fonts, Images): Cache First
 *  - PHP Pages (PDV, Pré-Venda, etc.): Network First, Cache Fallback
 *  - API Requests (GET): Network First, Cache Fallback
 *  - API Requests (POST): Pass-through (handled by offline-bridge.js)
 * 
 * IMPORTANTE: Este SW NÃO altera nenhuma funcionalidade existente.
 * Ele apenas adiciona uma camada de cache para resiliência offline.
 */

const CACHE_VERSION = 'erp-eletrica-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const PAGES_CACHE = `${CACHE_VERSION}-pages`;
const API_CACHE = `${CACHE_VERSION}-api`;
const IMG_CACHE = `${CACHE_VERSION}-images`;

// Assets que serão pré-cacheados na instalação
const PRECACHE_ASSETS = [
    // CSS
    'public/css/corporate.css',
    'style.css',
    // JS
    'public/js/corporate.js',
    'public/js/offline-bridge.js',
    'script.js',
    // CDN (critical)
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
];

// Páginas que DEVEM funcionar offline (serão cacheadas no primeiro acesso)
const CRITICAL_PAGES = [
    'vendas.php',
    'pre_vendas.php',
    'caixa.php',
];

// Padrões de URL para identificar tipo de recurso
const isStaticAsset = (url) => {
    return /\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)(\?.*)?$/i.test(url) ||
           url.includes('cdn.jsdelivr.net') ||
           url.includes('cdnjs.cloudflare.com') ||
           url.includes('fonts.googleapis.com') ||
           url.includes('fonts.gstatic.com');
};

const isImageAsset = (url) => {
    return /\.(png|jpg|jpeg|gif|webp|svg|ico)(\?.*)?$/i.test(url) ||
           url.includes('public/uploads/');
};

const isAPIRequest = (url) => {
    return url.includes('action=') && !url.includes('action=index');
};

const isPHPPage = (url) => {
    return url.endsWith('.php') || url.endsWith('/');
};

// ===== INSTALL =====
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando Service Worker v1...');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Pré-cacheando assets estáticos...');
                // Use addAll with error tolerance — some CDN URLs might fail on first install
                return Promise.allSettled(
                    PRECACHE_ASSETS.map(url => 
                        cache.add(url).catch(err => 
                            console.warn(`[SW] Falha ao cachear: ${url}`, err)
                        )
                    )
                );
            })
            .then(() => {
                console.log('[SW] Assets pré-cacheados com sucesso');
                return self.skipWaiting();
            })
    );
});

// ===== ACTIVATE =====
self.addEventListener('activate', (event) => {
    console.log('[SW] Ativando Service Worker v1...');
    event.waitUntil(
        caches.keys()
            .then(names => {
                return Promise.all(
                    names
                        .filter(name => name.startsWith('erp-eletrica-') && name !== STATIC_CACHE && name !== PAGES_CACHE && name !== API_CACHE && name !== IMG_CACHE)
                        .map(name => {
                            console.log(`[SW] Removendo cache antigo: ${name}`);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

// ===== FETCH =====
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = request.url;

    // Nunca interceptar POST — o offline-bridge.js cuida disso
    if (request.method !== 'GET') {
        return;
    }

    // Nunca interceptar requests de login/logout
    if (url.includes('login.php') || url.includes('logout.php')) {
        return;
    }

    // Estratégia por tipo de recurso
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
    } else if (isImageAsset(url)) {
        event.respondWith(cacheFirst(request, IMG_CACHE));
    } else if (isAPIRequest(url)) {
        event.respondWith(networkFirst(request, API_CACHE, 5000));
    } else if (isPHPPage(url)) {
        event.respondWith(networkFirst(request, PAGES_CACHE, 8000));
    }
    // Qualquer outra coisa: comportamento padrão do browser
});

// ===== ESTRATÉGIAS DE CACHE =====

/**
 * Cache First: Tenta servir do cache, se não tiver vai na rede.
 * Ideal para assets estáticos que mudam raramente.
 */
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    
    if (cached) {
        // Atualiza cache em background (stale-while-revalidate)
        fetchAndCache(request, cacheName).catch(() => {});
        return cached;
    }
    
    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        console.warn(`[SW] Cache miss e rede indisponível: ${request.url}`);
        return new Response('Recurso indisponível offline', { status: 503 });
    }
}

/**
 * Network First: Tenta a rede primeiro (com timeout), cai pro cache se falhar.
 * Ideal para páginas PHP e APIs de leitura.
 */
async function networkFirst(request, cacheName, timeout = 5000) {
    const cache = await caches.open(cacheName);
    
    try {
        const response = await fetchWithTimeout(request, timeout);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        console.log(`[SW] Rede falhou, servindo do cache: ${request.url}`);
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }
        
        // Se nem cache tem, retorna erro genérico
        console.warn(`[SW] Sem cache para: ${request.url}`);
        return new Response(JSON.stringify({ 
            error: 'offline', 
            message: 'Recurso indisponível no modo offline' 
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

/**
 * Fetch com timeout para evitar esperas longas quando a rede está ruim.
 */
function fetchWithTimeout(request, timeout) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Timeout')), timeout);
        
        fetch(request)
            .then(response => {
                clearTimeout(timer);
                resolve(response);
            })
            .catch(err => {
                clearTimeout(timer);
                reject(err);
            });
    });
}

/**
 * Atualiza o cache em background sem bloquear a resposta.
 */
async function fetchAndCache(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
    } catch (err) {
        // Silêncio — atualização em background não deve gerar erro
    }
}

// ===== MENSAGENS DO MAIN THREAD =====
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CACHE_PAGE') {
        // O offline-bridge.js pode pedir para cachear uma página específica
        caches.open(PAGES_CACHE).then(cache => {
            cache.add(event.data.url).catch(err => 
                console.warn('[SW] Falha ao cachear página solicitada:', err)
            );
        });
    }
    
    if (event.data && event.data.type === 'CLEAR_API_CACHE') {
        // Limpar cache de API após sincronização
        caches.delete(API_CACHE).then(() => {
            console.log('[SW] Cache de API limpo após sincronização');
        });
    }
});
