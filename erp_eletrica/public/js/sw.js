/**
 * ERP Elétrica — Service Worker v4
 * 
 * REGRAS:
 *  1. Cacheia APENAS as 3 páginas críticas: vendas.php, pre_vendas.php, caixa.php
 *  2. Cacheia assets estáticos necessários (CSS, JS, fontes, CDN)
 *  3. NÃO intercepta nenhuma outra página PHP
 *  4. NÃO intercepta requisições com ?action= (API calls — o offline-bridge.js cuida)
 *  5. NÃO intercepta POST requests
 */

const CACHE_NAME = 'erp-v4';

// Expressões para identificar páginas críticas (SEM query params de action)
function isCriticalPage(url) {
    try {
        const u = new URL(url);
        // Só cacheia se NÃO tem action= (page load, não API call)
        if (u.searchParams.has('action')) return false;
        const path = u.pathname;
        return path.endsWith('vendas.php') || 
               path.endsWith('pre_vendas.php') || 
               path.endsWith('caixa.php');
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

// ===== INSTALL =====
self.addEventListener('install', (event) => {
    console.log('[SW v4] Instalando...');
    // Limpar caches antigos e tomar controle imediatamente
    event.waitUntil(
        caches.keys()
            .then(names => Promise.all(
                names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n))
            ))
            .then(() => self.skipWaiting())
    );
});

// ===== ACTIVATE =====
self.addEventListener('activate', (event) => {
    console.log('[SW v4] Ativado');
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

    // 2) Assets estáticos (CSS, JS, Fontes) → Cache First
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirstAsset(req));
        return;
    }

    // 3) Imagens → Cache First (com limite)
    if (isImage(url)) {
        event.respondWith(cacheFirstAsset(req));
        return;
    }

    // QUALQUER OUTRA COISA: NÃO interceptar (browser padrão)
    // Isso inclui todas as outras páginas PHP → sem tela preta
});

/**
 * Network First para páginas críticas.
 * Quando online: busca do servidor e salva no cache.
 * Quando offline: serve a versão cacheada.
 */
async function networkFirstPage(request) {
    const cache = await caches.open(CACHE_NAME);

    try {
        // Tenta buscar da rede (sem timeout — não queremos interferir)
        const response = await fetch(request);
        
        // Só cacheia respostas válidas (200 OK, com HTML)
        if (response.ok && response.headers.get('content-type')?.includes('text/html')) {
            cache.put(request, response.clone());
            console.log('[SW v4] Página cacheada:', request.url);
        }
        
        return response;
    } catch (err) {
        // Rede falhou → tenta servir do cache
        console.log('[SW v4] Offline — servindo do cache:', request.url);
        const cached = await cache.match(request);
        
        if (cached) {
            return cached;
        }

        // Sem cache → página de erro amigável
        return new Response(`
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Sem Conexão — ERP Elétrica</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: 'Inter', 'Segoe UI', sans-serif;
                        display: flex; align-items: center; justify-content: center;
                        min-height: 100vh; background: #0f172a; color: #e2e8f0;
                        text-align: center; padding: 2rem;
                    }
                    .container { max-width: 480px; }
                    .icon { font-size: 5rem; margin-bottom: 1.5rem; }
                    h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #f59e0b; }
                    p { color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem; }
                    .tip {
                        background: #1e293b; border: 1px solid #334155;
                        border-radius: 12px; padding: 1rem; font-size: 0.85rem;
                        color: #cbd5e1;
                    }
                    .tip strong { color: #60a5fa; }
                    .btn {
                        display: inline-block; margin-top: 1.5rem;
                        padding: 12px 24px; background: #3b82f6; color: #fff;
                        border: none; border-radius: 8px; font-size: 1rem;
                        font-weight: 600; cursor: pointer; text-decoration: none;
                    }
                    .btn:hover { background: #2563eb; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="icon">⚡</div>
                    <h1>Sem Conexão com a Internet</h1>
                    <p>Esta página precisa ser carregada pelo menos uma vez com internet para funcionar offline.</p>
                    <div class="tip">
                        <strong>💡 Dica:</strong> Abra a tela de <strong>Vendas</strong>, <strong>Pré-Venda</strong> 
                        ou <strong>Caixa</strong> enquanto estiver online. Depois, mesmo sem internet, 
                        essas telas continuarão funcionando.
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
}

/**
 * Cache First para assets estáticos.
 * Serve do cache quando disponível, atualiza em background.
 */
async function cacheFirstAsset(request) {
    const cache = await caches.open(CACHE_NAME);

    try {
        const cached = await cache.match(request);
        if (cached) {
            // Atualiza em background (Stale While Revalidate)
            fetch(request).then(res => {
                if (res.ok) cache.put(request, res);
            }).catch(() => {});
            return cached;
        }

        // Não está no cache → busca da rede
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        // Tenta cache antigo
        const cached = await cache.match(request);
        if (cached) return cached;

        // Sem cache → let it fail naturally
        return new Response('', { status: 503 });
    }
}
