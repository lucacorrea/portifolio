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

12: const CACHE_NAME = 'erp-v5';
13: 
14: // Expressões para identificar páginas críticas (SEM query params de action)
15: function isCriticalPage(url) {
16:     try {
17:         const u = new URL(url);
18:         // Só cacheia se NÃO tem action= (page load, não API call)
19:         if (u.searchParams.has('action')) return false;
20:         const path = u.pathname;
21:         return path.endsWith('vendas.php') || 
22:                path.endsWith('pre_vendas.php') || 
23:                path.endsWith('caixa.php');
24:     } catch(e) { return false; }
25: }
26: 
27: function isStaticAsset(url) {
28:     return /\.(css|js|woff2?|ttf|eot)(\?.*)?$/i.test(url) ||
29:            url.includes('cdn.jsdelivr.net') ||
30:            url.includes('cdnjs.cloudflare.com') ||
31:            url.includes('fonts.googleapis.com') ||
32:            url.includes('fonts.gstatic.com');
33: }
34: 
35: function isImage(url) {
36:     return /\.(png|jpg|jpeg|gif|webp|svg|ico)(\?.*)?$/i.test(url);
37: }
38: 
39: // ===== INSTALL =====
40: self.addEventListener('install', (event) => {
41:     console.log('[SW v5] Instalando...');
42:     event.waitUntil(
43:         caches.keys()
44:             .then(names => Promise.all(
45:                 names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n))
46:             ))
47:             .then(() => self.skipWaiting())
48:     );
49: });
50: 
51: // ===== ACTIVATE =====
52: self.addEventListener('activate', (event) => {
53:     console.log('[SW v5] Ativado');
54:     event.waitUntil(self.clients.claim());
55: });
56: 
57: // ===== FETCH =====
58: self.addEventListener('fetch', (event) => {
59:     const req = event.request;
60:     const url = req.url;
61: 
62:     // NUNCA interceptar POST
63:     if (req.method !== 'GET') return;
64: 
65:     // NUNCA interceptar login, logout, api_sync
66:     if (url.includes('login.php') || url.includes('logout.php') || url.includes('api_sync.php')) return;
67: 
68:     // NUNCA interceptar requests com ?action= (API calls — offline-bridge cuida)
69:     if (url.includes('action=')) return;
70: 
71:     // 1) Páginas críticas → Network First, Cache Fallback
72:     if (isCriticalPage(url)) {
73:         event.respondWith(networkFirstPage(req));
74:         return;
75:     }
76: 
77:     // 2) Assets estáticos (CSS, JS, Fontes) → Cache First (Stale While Revalidate)
78:     if (isStaticAsset(url)) {
79:         event.respondWith(cacheFirstAsset(req));
80:         return;
81:     }
82: 
83:     // 3) Imagens → Cache First com gerenciamento de espaço
84:     if (isImage(url)) {
85:         event.respondWith(cacheFirstAsset(req, true)); // true = is image
86:         return;
87:     }
88: });
89: 
90: /**
91:  * Network First para páginas críticas.
92:  */
93: async function networkFirstPage(request) {
94:     const cache = await caches.open(CACHE_NAME);
95:     try {
96:         const response = await fetch(request);
97:         if (response.ok && response.headers.get('content-type')?.includes('text/html')) {
98:             cache.put(request, response.clone());
99:         }
100:         return response;
101:     } catch (err) {
102:         const cached = await cache.match(request);
103:         return cached || serveErrorPage();
104:     }
105: }
106: 
107: /**
108:  * Cache First (Stale-While-Revalidate) para assets e imagens.
109:  */
110: async function cacheFirstAsset(request, isImage = false) {
111:     const cache = await caches.open(CACHE_NAME);
112:     const cached = await cache.match(request);
113:     
114:     const fetchPromise = fetch(request).then(response => {
115:         if (response.ok) {
116:             if (isImage) limitCacheSize(cache, 50); // Limite de 50 imagens
117:             cache.put(request, response.clone());
118:         }
119:         return response;
120:     }).catch(() => {});
121: 
122:     return cached || fetchPromise;
123: }
124: 
125: /**
126:  * Limita o tamanho do cache removendo os itens mais antigos.
127:  */
128: async function limitCacheSize(cache, maxItems) {
129:     const keys = await cache.keys();
130:     if (keys.length > maxItems) {
131:         await cache.delete(keys[0]);
132:         limitCacheSize(cache, maxItems);
133:     }
134: }
135: 
136: function serveErrorPage() {
137:     return new Response(`
138:         <!DOCTYPE html>
139:         <html lang="pt-BR">
140:         <head>
141:             <meta charset="UTF-8">
142:             <meta name="viewport" content="width=device-width, initial-scale=1.0">
143:             <title>Modo Offline — ERP Elétrica</title>
144:             <style>
145:                 body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; background: #0f172a; color: #fff; text-align: center; }
146:                 .box { padding: 2rem; border-radius: 1rem; background: #1e293b; max-width: 400px; }
147:                 button { margin-top: 1rem; padding: 10px 20px; border: none; border-radius: 5px; background: #3b82f6; color: #fff; cursor: pointer; }
148:             </style>
149:         </head>
150:         <body>
151:             <div class="box">
152:                 <h2>Página não Carregada</h2>
153:                 <p>Você está offline e esta página ainda não foi salva no cache.</p>
154:                 <button onclick="location.reload()">🔄 Tentar Novamente</button>
155:             </div>
156:         </body>
157:         </html>
158:     `, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
159: }
160: 
161: // ===== MENSAGENS =====
162: self.addEventListener('message', (event) => {
163:     if (event.data?.type === 'SKIP_WAITING') {
164:         self.skipWaiting();
165:     }
166: });


// ===== MENSAGENS =====
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
