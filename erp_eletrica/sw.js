/**
 * ERP Elétrica — Service Worker v3 (Auto-limpeza)
 * 
 * Este SW NÃO faz nada além de se auto-desinstalar e limpar caches antigos.
 * A lógica offline é gerenciada exclusivamente pelo offline-bridge.js.
 */

// Na instalação, toma controle imediatamente
self.addEventListener('install', () => self.skipWaiting());

// Na ativação, limpa TODOS os caches antigos e toma controle
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then(names => Promise.all(names.map(name => caches.delete(name))))
            .then(() => {
                console.log('[SW] Todos os caches limpos');
                return self.clients.claim();
            })
    );
});

// NÃO intercepta NENHUM fetch — deixa tudo passar naturalmente
// O offline-bridge.js no main thread cuida de tudo
