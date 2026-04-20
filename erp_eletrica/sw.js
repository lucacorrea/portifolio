/**
 * ERP Elétrica — Service Worker v6
 * 
 * REGRAS:
 *  1. PRÉ-CACHEIA as 4 páginas críticas no INSTALL (vendas, pre_vendas, caixa, estoque)
 *  2. Cacheia assets estáticos necessários (CSS, JS, fontes, CDN)
 *  3. NÃO intercepta nenhuma outra página PHP
 *  4. NÃO intercepta requisições com ?action= (API calls — o offline-bridge.js cuida)
 *  5. NÃO intercepta POST requests
 *  6. Páginas críticas: Network First + Cache Fallback (atualiza cache quando online)
 *  7. Cache PERSISTE entre sessões — abrir uma vez já salva pra sempre
 */

const CACHE_NAME = 'erp-v6';

// Páginas que devem ser pré-cacheadas e funcionar offline
const CRITICAL_PAGES = [
    'vendas.php',
    'pre_vendas.php',
    'caixa.php',
    'estoque.php',
    'consulta_produto.php'
];

// Assets estáticos essenciais para pré-cachear
const CRITICAL_ASSETS = [
    'public/css/corporate.css',
    'public/js/offline-bridge.js',
    'public/js/corporate.js',
    'public/img/app-icon.png',
    'manifest.json',
    'script.js',
    'style.css'
];

function isCriticalPage(url) {
    try {
        const u = new URL(url);
        if (u.searchParams.has('action')) return false;
        const path = u.pathname;
        return path.endsWith('vendas.php') || 
               path.endsWith('pre_vendas.php') || 
               path.endsWith('caixa.php') ||
               path.endsWith('estoque.php') ||
               path.endsWith('consulta_produto.php');
    } catch(e) { return false; }
}

function isStaticAsset(url) {
    return /\.(css|js|woff2?|ttf|eot)(\?.*)?$/i.test(url) ||
           url.includes('cdn.jsdelivr.net') ||
           url.includes('cdnjs.cloudflare.com') ||
           url.includes('fonts.googleapis.com') ||
           url.includes('fonts.gstatic.com');
}

function isImage(url) {
    return /\.(png|jpg|jpeg|gif|webp|svg|ico)(\?.*)?$/i.test(url);
}

// ===== INSTALL — Pré-cacheia páginas críticas =====
self.addEventListener('install', (event) => {
    console.log('[SW v6] Instalando com pré-cache...');
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            // Limpar caches antigos
            const names = await caches.keys();
            await Promise.all(names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n)));

            // Pré-cachear assets estáticos (falha silenciosa)
            for (const asset of CRITICAL_ASSETS) {
                try {
                    await cache.add(asset);
                    console.log('[SW v6] Asset pré-cacheado:', asset);
                } catch (e) {
                    console.warn('[SW v6] Falha ao pré-cachear asset:', asset, e.message);
                }
            }

            // Pré-cachear páginas críticas (falha silenciosa)
            for (const page of CRITICAL_PAGES) {
                try {
                    const response = await fetch(page, { credentials: 'include' });
                    if (response.ok) {
                        await cache.put(page, response);
                        console.log('[SW v6] Página pré-cacheada:', page);
                    }
                } catch (e) {
                    console.warn('[SW v6] Falha ao pré-cachear página:', page, e.message);
                }
            }

            return self.skipWaiting();
        })
    );
});

// ===== ACTIVATE =====
self.addEventListener('activate', (event) => {
    console.log('[SW v6] Ativado — tomando controle');
    event.waitUntil(self.clients.claim());
});

// ===== FETCH =====
self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = req.url;

    // NUNCA interceptar POST
    if (req.method !== 'GET') return;

    // NUNCA interceptar login, logout, api_sync
    if (url.includes('login.php') || url.includes('logout.php') || url.includes('api_sync.php')) return;

    // NUNCA interceptar requests com ?action= (API calls — offline-bridge cuida)
    if (url.includes('action=')) return;

    // 1) Páginas críticas → Network First, Cache Fallback
    if (isCriticalPage(url)) {
        event.respondWith(networkFirstPage(req));
        return;
    }

    // 2) Assets estáticos → Stale While Revalidate
    if (isStaticAsset(url)) {
        event.respondWith(staleWhileRevalidate(req));
        return;
    }

    // 3) Imagens → Cache First com limite
    if (isImage(url)) {
        event.respondWith(cacheFirstImage(req));
        return;
    }

    // QUALQUER OUTRA COISA: browser padrão (não interceptar)
});

/**
 * Network First para páginas críticas.
 * Online: busca do servidor, atualiza cache.
 * Offline: serve do cache salvo.
 */
async function networkFirstPage(request) {
    const cache = await caches.open(CACHE_NAME);
    try {
        const response = await fetch(request);
        if (response.ok && response.headers.get('content-type')?.includes('text/html')) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        // Offline → tenta o cache com e sem query string
        const cached = await cache.match(request);
        if (cached) return cached;

        // Tentar match pela URL base (sem query params)
        const url = new URL(request.url);
        const basePath = url.pathname.split('/').pop();
        const cachedByBase = await cache.match(basePath);
        if (cachedByBase) return cachedByBase;

        return serveErrorPage();
    }
}

/**
 * Stale While Revalidate para assets.
 * Serve do cache imediatamente, atualiza em background.
 */
async function staleWhileRevalidate(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request).then(response => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);

    return cached || (await fetchPromise) || new Response('', { status: 503 });
}

/**
 * Cache First para imagens com limite de espaço.
 */
async function cacheFirstImage(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            // Limitar quantidade de imagens no cache
            const keys = await cache.keys();
            const imgKeys = keys.filter(k => /\.(png|jpg|jpeg|gif|webp|svg|ico)/i.test(k.url));
            if (imgKeys.length > 100) {
                await cache.delete(imgKeys[0]);
            }
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        return new Response('', { status: 503 });
    }
}

function serveErrorPage() {
    return new Response(`
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modo Offline — ERP Elétrica</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; background: #0f172a; color: #e2e8f0;
            text-align: center; padding: 2rem;
        }
        .container { max-width: 420px; }
        .icon { font-size: 4rem; margin-bottom: 1rem; }
        h1 { font-size: 1.4rem; margin-bottom: 0.5rem; color: #f59e0b; }
        p { color: #94a3b8; line-height: 1.6; margin-bottom: 1rem; font-size: 0.9rem; }
        .tip {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 12px; padding: 1rem; font-size: 0.8rem;
            color: #cbd5e1;
        }
        .tip strong { color: #60a5fa; }
        .btn {
            display: inline-block; margin-top: 1.5rem;
            padding: 12px 24px; background: #3b82f6; color: #fff;
            border: none; border-radius: 8px; font-size: 0.95rem;
            font-weight: 600; cursor: pointer; text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⚡</div>
        <h1>Sem Conexão com a Internet</h1>
        <p>Esta página precisa ser carregada pelo menos uma vez com internet para funcionar offline.</p>
        <div class="tip">
            <strong>💡 Dica:</strong> Abra a tela de <strong>Vendas</strong>, <strong>Pré-Venda</strong>,
            <strong>Caixa</strong> ou <strong>Estoque</strong> enquanto estiver online.
            Depois, essas telas funcionarão sem internet.
        </div>
        <a class="btn" href="javascript:location.reload()">🔄 Tentar Novamente</a>
    </div>
</body>
</html>
    `, {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
    });
}

// ===== MENSAGENS =====
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
