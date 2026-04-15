/**
 * =============================================================================
 * ERP Elétrica — Offline Bridge (Modo Híbrido)
 * =============================================================================
 * 
 * Este módulo intercepta TRANSPARENTEMENTE todas as chamadas fetch() do sistema
 * para as telas de Venda (PDV) e Pré-Venda, permitindo operação offline.
 * 
 * NENHUMA ALTERAÇÃO VISUAL é feita. O módulo:
 *  1. Detecta status de conexão (heartbeat + navigator.onLine)
 *  2. Cacheia produtos e clientes no IndexedDB para buscas offline
 *  3. Enfileira vendas e pré-vendas no IndexedDB quando offline
 *  4. Sincroniza automaticamente quando a internet volta
 *  5. Gera logs detalhados de tudo
 * 
 * @version 1.0.0
 * =============================================================================
 */

(function() {
    'use strict';

    // =========================================================================
    // CONFIGURAÇÃO
    // =========================================================================
    const CONFIG = {
        DB_NAME: 'erp_eletrica_offline',
        DB_VERSION: 1,
        HEARTBEAT_INTERVAL: 15000,       // 15 segundos
        CACHE_REFRESH_INTERVAL: 300000,  // 5 minutos
        SYNC_INTERVAL: 10000,            // 10 segundos quando online
        FETCH_TIMEOUT: 15000,            // 15 segundos de timeout (Hostinger pode ser lento)
        SYNC_ENDPOINT: 'api_sync.php',
        HEARTBEAT_ENDPOINT: 'api_sync.php?action=heartbeat',
        CACHE_PRODUCTS_ENDPOINT: 'api_sync.php?action=cache_products',
        CACHE_CLIENTS_ENDPOINT: 'api_sync.php?action=cache_clients',
        MAX_LOG_ENTRIES: 5000,
        LOG_RETENTION_DAYS: 30,
    };

    // =========================================================================
    // LOGGER — Logs detalhados em console + IndexedDB
    // =========================================================================
    const Logger = {
        _buffer: [],

        log(category, message, data = null) {
            const entry = {
                timestamp: new Date().toISOString(),
                category,
                message,
                data: data ? JSON.stringify(data) : null,
                level: 'INFO'
            };
            console.log(`[ERP-OFFLINE][${category}] ${message}`, data || '');
            this._buffer.push(entry);
            this._flush();
        },

        warn(category, message, data = null) {
            const entry = {
                timestamp: new Date().toISOString(),
                category,
                message,
                data: data ? JSON.stringify(data) : null,
                level: 'WARN'
            };
            console.warn(`[ERP-OFFLINE][${category}] ⚠️ ${message}`, data || '');
            this._buffer.push(entry);
            this._flush();
        },

        error(category, message, data = null) {
            const entry = {
                timestamp: new Date().toISOString(),
                category,
                message,
                data: data ? (data instanceof Error ? data.message : JSON.stringify(data)) : null,
                level: 'ERROR'
            };
            console.error(`[ERP-OFFLINE][${category}] ❌ ${message}`, data || '');
            this._buffer.push(entry);
            this._flush();
        },

        async _flush() {
            if (this._buffer.length < 5 && !this._forceFlush) return;
            this._forceFlush = false;

            const entries = this._buffer.splice(0);
            try {
                const db = await OfflineDB.getDB();
                const tx = db.transaction('sync_log', 'readwrite');
                const store = tx.objectStore('sync_log');
                for (const entry of entries) {
                    store.add(entry);
                }
                await tx.done;
            } catch (err) {
                // Fallback: print to console only
                console.warn('[ERP-OFFLINE] Erro ao salvar logs no IndexedDB:', err);
            }
        },

        async forceFlush() {
            this._forceFlush = true;
            await this._flush();
        }
    };

    // =========================================================================
    // IndexedDB MANAGER
    // =========================================================================
    const OfflineDB = {
        _db: null,
        _dbPromise: null,

        async getDB() {
            if (this._db) return this._db;
            if (this._dbPromise) return this._dbPromise;

            this._dbPromise = new Promise((resolve, reject) => {
                const request = indexedDB.open(CONFIG.DB_NAME, CONFIG.DB_VERSION);

                request.onupgradeneeded = (event) => {
                    const db = event.target.result;

                    // Fila de operações offline (vendas, pré-vendas)
                    if (!db.objectStoreNames.contains('offline_queue')) {
                        const queueStore = db.createObjectStore('offline_queue', { 
                            keyPath: 'id', autoIncrement: true 
                        });
                        queueStore.createIndex('type', 'type', { unique: false });
                        queueStore.createIndex('status', 'status', { unique: false });
                        queueStore.createIndex('created_at', 'created_at', { unique: false });
                    }

                    // Cache de produtos
                    if (!db.objectStoreNames.contains('cached_products')) {
                        const prodStore = db.createObjectStore('cached_products', { 
                            keyPath: 'id' 
                        });
                        prodStore.createIndex('nome', 'nome', { unique: false });
                        prodStore.createIndex('codigo', 'codigo', { unique: false });
                    }

                    // Cache de clientes
                    if (!db.objectStoreNames.contains('cached_clients')) {
                        const clientStore = db.createObjectStore('cached_clients', { 
                            keyPath: 'id' 
                        });
                        clientStore.createIndex('nome', 'nome', { unique: false });
                        clientStore.createIndex('cpf_cnpj', 'cpf_cnpj', { unique: false });
                    }

                    // Cache de pré-vendas pendentes
                    if (!db.objectStoreNames.contains('cached_presales')) {
                        const pvStore = db.createObjectStore('cached_presales', { 
                            keyPath: 'id' 
                        });
                        pvStore.createIndex('codigo', 'codigo', { unique: false });
                        pvStore.createIndex('status', 'status', { unique: false });
                    }

                    // Log de sincronização
                    if (!db.objectStoreNames.contains('sync_log')) {
                        const logStore = db.createObjectStore('sync_log', { 
                            keyPath: 'id', autoIncrement: true 
                        });
                        logStore.createIndex('timestamp', 'timestamp', { unique: false });
                        logStore.createIndex('category', 'category', { unique: false });
                        logStore.createIndex('level', 'level', { unique: false });
                    }

                    // Dados de sessão (clonados do PHP)
                    if (!db.objectStoreNames.contains('session_data')) {
                        db.createObjectStore('session_data', { keyPath: 'key' });
                    }

                    Logger.log('DB', 'IndexedDB criado/atualizado com sucesso');
                };

                request.onsuccess = (event) => {
                    this._db = event.target.result;
                    resolve(this._db);
                };

                request.onerror = (event) => {
                    Logger.error('DB', 'Falha ao abrir IndexedDB', event.target.error);
                    reject(event.target.error);
                };
            });

            return this._dbPromise;
        },

        // --- Operações genéricas ---
        async put(storeName, data) {
            const db = await this.getDB();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, 'readwrite');
                const store = tx.objectStore(storeName);
                const request = store.put(data);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        async add(storeName, data) {
            const db = await this.getDB();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, 'readwrite');
                const store = tx.objectStore(storeName);
                const request = store.add(data);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        async get(storeName, key) {
            const db = await this.getDB();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, 'readonly');
                const store = tx.objectStore(storeName);
                const request = store.get(key);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        async getAll(storeName) {
            const db = await this.getDB();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, 'readonly');
                const store = tx.objectStore(storeName);
                const request = store.getAll();
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        async delete(storeName, key) {
            const db = await this.getDB();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, 'readwrite');
                const store = tx.objectStore(storeName);
                const request = store.delete(key);
                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        },

        async clear(storeName) {
            const db = await this.getDB();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, 'readwrite');
                const store = tx.objectStore(storeName);
                const request = store.clear();
                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        },

        async count(storeName) {
            const db = await this.getDB();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, 'readonly');
                const store = tx.objectStore(storeName);
                const request = store.count();
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Busca textual nos produtos cacheados (simula o search do servidor)
         */
        async searchProducts(term) {
            const allProducts = await this.getAll('cached_products');
            const termLower = term.toLowerCase().trim();

            return allProducts
                .filter(p => {
                    const nome = (p.nome || '').toLowerCase();
                    const codigo = (p.codigo || '').toLowerCase();
                    const id = String(p.id);
                    return nome.includes(termLower) || 
                           codigo.includes(termLower) || 
                           codigo === termLower ||
                           id === termLower;
                })
                .slice(0, 15)
                .map(p => ({
                    id: p.id,
                    nome: p.nome,
                    preco_venda: p.preco_venda,
                    unidade: p.unidade || 'UN',
                    imagens: p.imagens || '',
                    codigo: p.codigo || '',
                    type: 'product',
                    stock_qty: p.stock_qty || 0,
                    _offline: true
                }));
        },

        /**
         * Busca textual nos clientes cacheados
         */
        async searchClients(term) {
            const allClients = await this.getAll('cached_clients');
            const termLower = term.toLowerCase().trim();

            return allClients
                .filter(c => {
                    const nome = (c.nome || '').toLowerCase();
                    const doc = (c.cpf_cnpj || c.doc || '').toLowerCase();
                    return nome.includes(termLower) || doc.includes(termLower);
                })
                .slice(0, 10)
                .map(c => ({
                    id: c.id,
                    nome: c.nome,
                    doc: c.cpf_cnpj || c.doc || ''
                }));
        },

        /**
         * Busca pré-vendas pendentes cacheadas + criadas offline
         */
        async searchPreSales(term) {
            const cached = await this.getAll('cached_presales');
            
            // Também incluir pré-vendas criadas offline
            const offlineQueue = await this.getAll('offline_queue');
            const offlinePVs = offlineQueue
                .filter(q => q.type === 'presale' && q.status === 'pending')
                .map(q => ({
                    id: q.temp_id,
                    codigo: q.data.codigo || q.temp_code,
                    valor_total: q.data.valor_total,
                    status: 'pendente',
                    cliente_nome: q.data.nome_cliente_avulso || 'Consumidor',
                    vendedor_nome: q.session.usuario_nome || '',
                    _offline: true
                }));

            let results = [...offlinePVs, ...cached];

            if (term) {
                const termLower = term.toLowerCase().trim();
                results = results.filter(pv => {
                    const codigo = (pv.codigo || '').toLowerCase();
                    const cliente = (pv.cliente_nome || '').toLowerCase();
                    return codigo.includes(termLower) || 
                           cliente.includes(termLower) ||
                           String(pv.id) === termLower;
                });
            } else {
                results = results.filter(pv => pv.status === 'pendente');
            }

            return results.slice(0, 30);
        },

        /**
         * Busca pré-venda por código (cacheada ou offline)
         */
        async findPreSaleByCode(code) {
            // Primeiro verifica pré-vendas offline
            const offlineQueue = await this.getAll('offline_queue');
            const offlinePV = offlineQueue.find(
                q => q.type === 'presale' && 
                     q.status === 'pending' && 
                     (q.data.codigo === code || q.temp_code === code)
            );

            if (offlinePV) {
                return {
                    id: offlinePV.temp_id,
                    codigo: offlinePV.data.codigo || offlinePV.temp_code,
                    valor_total: offlinePV.data.valor_total,
                    status: 'pendente',
                    cliente_id: offlinePV.data.cliente_id,
                    cliente_nome: offlinePV.data.nome_cliente_avulso || 'Consumidor',
                    nome_cliente_avulso: offlinePV.data.nome_cliente_avulso,
                    cliente_doc: offlinePV.data.cpf_cliente,
                    itens: (offlinePV.data.items || []).map(item => ({
                        produto_id: item.id,
                        produto_nome: item.nome,
                        preco_unitario: item.price,
                        quantidade: item.qty,
                        imagens: item.imagens || ''
                    })),
                    _offline: true
                };
            }

            // Depois verifica cache
            const cached = await this.getAll('cached_presales');
            return cached.find(pv => pv.codigo === code && pv.status === 'pendente') || null;
        }
    };

    // =========================================================================
    // CONNECTION MONITOR — Detecta online/offline com heartbeat
    // =========================================================================
    const ConnectionMonitor = {
        _isOnline: navigator.onLine,
        _listeners: [],
        _heartbeatTimer: null,
        _consecutiveFailures: 0,
        _rawFetch: window.fetch.bind(window), // Referência PURA do fetch (antes do override)

        get isOnline() {
            return this._isOnline;
        },

        init() {
            // Eventos nativos do browser
            window.addEventListener('online', () => this._handleOnline());
            window.addEventListener('offline', () => this._handleOffline());

            // Heartbeat contínuo (com delay para não atrapalhar o carregamento da página)
            setTimeout(() => {
                this._doHeartbeat();
                this._heartbeatTimer = setInterval(() => this._doHeartbeat(), CONFIG.HEARTBEAT_INTERVAL);
            }, 3000);

            Logger.log('CONN', `Status inicial: ${this._isOnline ? '🟢 ONLINE' : '🔴 OFFLINE'}`);
        },

        onChange(callback) {
            this._listeners.push(callback);
        },

        async _doHeartbeat() {
            try {
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 6000);

                // IMPORTANTE: Usa _rawFetch (fetch original) para NÃO ser interceptado
                const response = await this._rawFetch(CONFIG.HEARTBEAT_ENDPOINT, {
                    method: 'GET',
                    cache: 'no-store',
                    signal: controller.signal
                });

                clearTimeout(timeout);

                if (response.ok) {
                    this._consecutiveFailures = 0;
                    if (!this._isOnline) {
                        this._handleOnline();
                    }
                } else {
                    this._handleHeartbeatFailure();
                }
            } catch (err) {
                this._handleHeartbeatFailure();
            }
        },

        _handleHeartbeatFailure() {
            this._consecutiveFailures++;
            // Considera offline após 3 falhas consecutivas para evitar falsos positivos
            if (this._consecutiveFailures >= 3 && this._isOnline) {
                this._handleOffline();
            }
        },

        _handleOnline() {
            if (this._isOnline) return;
            this._isOnline = true;
            this._consecutiveFailures = 0;
            Logger.log('CONN', '🟢 Conexão RESTAURADA — Iniciando sincronização automática');
            this._notifyListeners('online');
        },

        _handleOffline() {
            if (!this._isOnline) return;
            this._isOnline = false;
            Logger.warn('CONN', '🔴 Conexão PERDIDA — Ativando modo offline');
            this._notifyListeners('offline');
        },

        _notifyListeners(status) {
            for (const cb of this._listeners) {
                try {
                    cb(status);
                } catch (err) {
                    Logger.error('CONN', 'Erro no listener de status:', err);
                }
            }
        }
    };

    // =========================================================================
    // NOTIFICATION MANAGER — Indicador visual discreto de status
    // =========================================================================
    const NotificationManager = {
        _indicator: null,
        _pendingBadge: null,

        init() {
            this._createIndicator();
            
            // Escutar mudanças de status
            ConnectionMonitor.onChange((status) => {
                this._updateIndicator(status === 'online');
            });
        },

        _createIndicator() {
            // Indicador discreto no rodapé, SEM alterar layout existente
            const indicator = document.createElement('div');
            indicator.id = 'erp-offline-indicator';
            indicator.style.cssText = `
                position: fixed; bottom: 10px; right: 10px; z-index: 99999;
                padding: 6px 14px; border-radius: 20px; font-size: 11px;
                font-weight: 600; font-family: 'Inter', sans-serif;
                display: flex; align-items: center; gap: 6px;
                transition: all 0.3s ease; pointer-events: none;
                opacity: 0; transform: translateY(10px);
                box-shadow: 0 2px 12px rgba(0,0,0,0.15);
            `;
            
            const badge = document.createElement('span');
            badge.id = 'erp-offline-pending-badge';
            badge.style.cssText = `
                background: #fff; color: #dc3545; border-radius: 50%;
                width: 18px; height: 18px; display: none;
                align-items: center; justify-content: center;
                font-size: 10px; font-weight: 700; line-height: 1;
            `;
            
            indicator.appendChild(badge);
            document.body.appendChild(indicator);
            
            this._indicator = indicator;
            this._pendingBadge = badge;
        },

        _updateIndicator(isOnline) {
            if (!this._indicator) return;

            if (isOnline) {
                // Mostrar brevemente e esconder
                this._indicator.style.background = '#10b981';
                this._indicator.style.color = '#fff';
                this._indicator.innerHTML = '<i class="fas fa-wifi" style="font-size:10px"></i> Online';
                this._show();
                setTimeout(() => this._hide(), 3000);
            } else {
                // Mostrar permanentemente quando offline
                this._indicator.style.background = '#ef4444';
                this._indicator.style.color = '#fff';
                this._indicator.innerHTML = '<i class="fas fa-wifi-slash" style="font-size:10px"></i> Modo Offline';
                if (this._pendingBadge) {
                    this._indicator.appendChild(this._pendingBadge);
                }
                this._show();
            }
        },

        updatePendingCount(count) {
            if (!this._pendingBadge) return;
            if (count > 0) {
                this._pendingBadge.style.display = 'flex';
                this._pendingBadge.textContent = count;
            } else {
                this._pendingBadge.style.display = 'none';
            }
        },

        showSyncProgress(message) {
            if (!this._indicator) return;
            this._indicator.style.background = '#3b82f6';
            this._indicator.style.color = '#fff';
            this._indicator.innerHTML = `<i class="fas fa-sync fa-spin" style="font-size:10px"></i> ${message}`;
            this._show();
        },

        _show() {
            if (this._indicator) {
                this._indicator.style.opacity = '1';
                this._indicator.style.transform = 'translateY(0)';
                this._indicator.style.pointerEvents = 'auto';
            }
        },

        _hide() {
            if (this._indicator) {
                this._indicator.style.opacity = '0';
                this._indicator.style.transform = 'translateY(10px)';
                this._indicator.style.pointerEvents = 'none';
            }
        }
    };

    // =========================================================================
    // CACHE MANAGER — Sincroniza produtos/clientes para buscas offline
    // =========================================================================
    const CacheManager = {
        _refreshTimer: null,

        async init() {
            // Cache inicial
            if (ConnectionMonitor.isOnline) {
                await this.refreshAll();
            }

            // Refresh periódico quando online
            this._refreshTimer = setInterval(() => {
                if (ConnectionMonitor.isOnline) {
                    this.refreshAll();
                }
            }, CONFIG.CACHE_REFRESH_INTERVAL);

            Logger.log('CACHE', 'Cache Manager inicializado');
        },

        async refreshAll() {
            try {
                await Promise.allSettled([
                    this.refreshProducts(),
                    this.refreshClients(),
                    this.refreshPreSales()
                ]);
            } catch (err) {
                Logger.warn('CACHE', 'Erro ao atualizar caches:', err);
            }
        },

        async refreshProducts() {
            try {
                const response = await fetch(CONFIG.CACHE_PRODUCTS_ENDPOINT, { cache: 'no-store' });
                if (!response.ok) return;
                
                const products = await response.json();
                if (!Array.isArray(products)) return;

                // Limpa e recarrega
                await OfflineDB.clear('cached_products');
                for (const product of products) {
                    await OfflineDB.put('cached_products', product);
                }

                Logger.log('CACHE', `Produtos cacheados: ${products.length} itens`);
            } catch (err) {
                Logger.warn('CACHE', 'Falha ao cachear produtos:', err);
            }
        },

        async refreshClients() {
            try {
                const response = await fetch(CONFIG.CACHE_CLIENTS_ENDPOINT, { cache: 'no-store' });
                if (!response.ok) return;
                
                const clients = await response.json();
                if (!Array.isArray(clients)) return;

                await OfflineDB.clear('cached_clients');
                for (const client of clients) {
                    await OfflineDB.put('cached_clients', client);
                }

                Logger.log('CACHE', `Clientes cacheados: ${clients.length} itens`);
            } catch (err) {
                Logger.warn('CACHE', 'Falha ao cachear clientes:', err);
            }
        },

        async refreshPreSales() {
            try {
                const response = await fetch('pre_vendas.php?action=list_pending', { cache: 'no-store' });
                if (!response.ok) return;

                const presales = await response.json();
                if (!Array.isArray(presales)) return;

                await OfflineDB.clear('cached_presales');
                for (const pv of presales) {
                    await OfflineDB.put('cached_presales', pv);
                }

                Logger.log('CACHE', `Pré-vendas pendentes cacheadas: ${presales.length} itens`);
            } catch (err) {
                Logger.warn('CACHE', 'Falha ao cachear pré-vendas:', err);
            }
        },

        /**
         * Cache incremental: adiciona resultados de busca ao cache existente
         */
        async cacheSearchResults(products) {
            try {
                for (const p of products) {
                    if (p.type !== 'pre_sale' && p.id) {
                        await OfflineDB.put('cached_products', p);
                    }
                }
            } catch (err) {
                // Silêncio
            }
        }
    };

    // =========================================================================
    // SYNC MANAGER — Processa fila offline quando a internet volta
    // =========================================================================
    const SyncManager = {
        _isSyncing: false,
        _syncTimer: null,

        init() {
            // Sincronizar quando a conexão é restaurada
            ConnectionMonitor.onChange(async (status) => {
                if (status === 'online') {
                    // Pequeno delay para garantir que a conexão estab estável
                    setTimeout(() => this.processQueue(), 2000);
                }
            });

            // Verificar fila periodicamente quando online
            this._syncTimer = setInterval(() => {
                if (ConnectionMonitor.isOnline && !this._isSyncing) {
                    this.processQueue();
                }
            }, CONFIG.SYNC_INTERVAL);

            Logger.log('SYNC', 'Sync Manager inicializado');
        },

        /**
         * Processa toda a fila de operações pendentes
         */
        async processQueue() {
            if (this._isSyncing) return;
            if (!ConnectionMonitor.isOnline) return;

            const pendingOps = await this._getPendingOps();
            if (pendingOps.length === 0) return;

            this._isSyncing = true;
            Logger.log('SYNC', `Iniciando sincronização de ${pendingOps.length} operações pendentes`);
            NotificationManager.showSyncProgress(`Sincronizando ${pendingOps.length} operações...`);

            let successCount = 0;
            let errorCount = 0;

            // Processar em ordem cronológica
            pendingOps.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));

            for (const op of pendingOps) {
                try {
                    await this._processOperation(op);
                    successCount++;
                    NotificationManager.showSyncProgress(
                        `Sincronizando... (${successCount}/${pendingOps.length})`
                    );
                } catch (err) {
                    errorCount++;
                    Logger.error('SYNC', `Falha ao sincronizar operação ${op.id}:`, err);

                    // Marcar como erro (não tentar novamente infinitamente)
                    op.retry_count = (op.retry_count || 0) + 1;
                    if (op.retry_count >= 5) {
                        op.status = 'error';
                        op.error_message = err.message || 'Erro desconhecido após 5 tentativas';
                        Logger.error('SYNC', `Operação ${op.id} marcada como ERRO permanente após 5 tentativas`, {
                            type: op.type, temp_id: op.temp_id
                        });
                    }
                    await OfflineDB.put('offline_queue', op);
                }
            }

            this._isSyncing = false;

            // Atualizar badge de pendentes
            const remaining = await this._getPendingOps();
            NotificationManager.updatePendingCount(remaining.length);

            if (successCount > 0) {
                Logger.log('SYNC', `Sincronização concluída: ${successCount} OK, ${errorCount} erros`);
                
                // Limpar cache de API no Service Worker para forçar dados frescos
                if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_API_CACHE' });
                }

                // Atualizar caches locais
                await CacheManager.refreshAll();
            }
        },

        /**
         * Processa uma operação individual da fila
         */
        async _processOperation(op) {
            Logger.log('SYNC', `Processando: ${op.type} (temp_id: ${op.temp_id})`);

            const payload = {
                action: 'sync_batch',
                operations: [{
                    type: op.type,
                    temp_id: op.temp_id,
                    temp_code: op.temp_code || null,
                    data: op.data,
                    session: op.session,
                    created_at: op.created_at,
                    is_contingencia: op.is_contingencia || false
                }]
            };

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);

            try {
                const response = await fetch(CONFIG.SYNC_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    signal: controller.signal,
                    cache: 'no-store'
                });

                clearTimeout(timeout);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();

                if (result.success) {
                    // Remover da fila
                    await OfflineDB.delete('offline_queue', op.id);

                    Logger.log('SYNC', `✅ Sincronizado: ${op.type} temp_id=${op.temp_id} → real_id=${result.results?.[0]?.real_id || '?'}`, {
                        temp_id: op.temp_id,
                        real_id: result.results?.[0]?.real_id,
                        real_code: result.results?.[0]?.real_code
                    });

                    // Se era pré-venda offline, atualizar referência no cache
                    if (op.type === 'presale' && result.results?.[0]?.real_code) {
                        // Atualizar qualquer venda na fila que referencie esta PV
                        await this._updatePVReferences(
                            op.temp_id, 
                            result.results[0].real_id, 
                            op.temp_code, 
                            result.results[0].real_code
                        );
                    }
                } else {
                    throw new Error(result.error || 'Erro desconhecido no servidor');
                }
            } catch (err) {
                clearTimeout(timeout);
                throw err;
            }
        },

        /**
         * Atualiza referências de PV temporária nas vendas da fila
         */
        async _updatePVReferences(tempPVId, realPVId, tempPVCode, realPVCode) {
            const allOps = await OfflineDB.getAll('offline_queue');
            for (const op of allOps) {
                if (op.type === 'sale' && op.data.pv_id === tempPVId) {
                    op.data.pv_id = realPVId;
                    await OfflineDB.put('offline_queue', op);
                    Logger.log('SYNC', `Referência PV atualizada na venda: ${tempPVId} → ${realPVId}`);
                }
            }
        },

        /**
         * Retorna operações pendentes (exceto erros permanentes)
         */
        async _getPendingOps() {
            const all = await OfflineDB.getAll('offline_queue');
            return all.filter(op => op.status === 'pending');
        }
    };

    // =========================================================================
    // OFFLINE QUEUE — Enfileira operações para sincronização posterior
    // =========================================================================
    const OfflineQueue = {
        _tempIdCounter: 0,

        /**
         * Gera ID temporário único para operações offline
         */
        generateTempId() {
            this._tempIdCounter++;
            return 'OFF-' + Date.now().toString(36).toUpperCase() + '-' + 
                   this._tempIdCounter.toString(36).toUpperCase() + '-' +
                   Math.random().toString(36).substr(2, 4).toUpperCase();
        },

        /**
         * Gera código de pré-venda temporário
         */
        generateTempPVCode() {
            return 'PV-OFF-' + Date.now().toString(36).toUpperCase().substr(-5);
        },

        /**
         * Obtém dados de sessão salvos
         */
        getSessionData() {
            // Os dados de sessão são injetados pelo PHP no main.view.php
            return {
                usuario_id: window.__ERP_SESSION?.usuario_id || null,
                usuario_nome: window.__ERP_SESSION?.usuario_nome || 'Desconhecido',
                filial_id: window.__ERP_SESSION?.filial_id || 1,
                is_matriz: window.__ERP_SESSION?.is_matriz || false,
                usuario_nivel: window.__ERP_SESSION?.usuario_nivel || 'operador'
            };
        },

        /**
         * Enfileira uma VENDA para sincronização
         */
        async enqueueSale(saleData) {
            const tempId = this.generateTempId();
            const session = this.getSessionData();

            // Determinar se é contingência fiscal
            const isContingencia = saleData.tipo_nota === 'fiscal';
            if (isContingencia) {
                saleData.tipo_nota = 'contingencia';
            }

            const queueEntry = {
                type: 'sale',
                temp_id: tempId,
                status: 'pending',
                data: saleData,
                session: session,
                created_at: new Date().toISOString(),
                retry_count: 0,
                is_contingencia: isContingencia
            };

            const queueId = await OfflineDB.add('offline_queue', queueEntry);

            Logger.log('QUEUE', `Venda enfileirada: temp_id=${tempId}, total=R$${saleData.total}, items=${saleData.items?.length || 0}`, {
                temp_id: tempId,
                total: saleData.total,
                pagamento: saleData.pagamento,
                tipo_nota: saleData.tipo_nota,
                contingencia: isContingencia,
                items_count: saleData.items?.length || 0
            });

            // Atualizar badge
            const pending = await OfflineDB.getAll('offline_queue');
            const pendingCount = pending.filter(p => p.status === 'pending').length;
            NotificationManager.updatePendingCount(pendingCount);

            return {
                success: true,
                sale_id: tempId,
                tipo_nota: saleData.tipo_nota,
                offline: true,
                contingencia: isContingencia
            };
        },

        /**
         * Enfileira uma PRÉ-VENDA para sincronização
         */
        async enqueuePreSale(pvData) {
            const tempId = this.generateTempId();
            const tempCode = this.generateTempPVCode();
            const session = this.getSessionData();

            const queueEntry = {
                type: 'presale',
                temp_id: tempId,
                temp_code: tempCode,
                status: 'pending',
                data: { ...pvData, codigo: tempCode },
                session: session,
                created_at: new Date().toISOString(),
                retry_count: 0
            };

            await OfflineDB.add('offline_queue', queueEntry);

            Logger.log('QUEUE', `Pré-Venda enfileirada: temp_id=${tempId}, code=${tempCode}, total=R$${pvData.valor_total}`, {
                temp_id: tempId,
                temp_code: tempCode,
                total: pvData.valor_total,
                items_count: pvData.items?.length || 0
            });

            // Atualizar badge
            const pending = await OfflineDB.getAll('offline_queue');
            const pendingCount = pending.filter(p => p.status === 'pending').length;
            NotificationManager.updatePendingCount(pendingCount);

            return {
                success: true,
                id: tempId,
                codigo: tempCode,
                offline: true
            };
        }
    };

    // =========================================================================
    // FETCH INTERCEPTOR — Intercepta transparentemente as chamadas fetch()
    // =========================================================================
    const FetchInterceptor = {
        _originalFetch: window.fetch.bind(window),

        init() {
            const self = this;

            // Substituir fetch global
            window.fetch = function(input, init) {
                const url = typeof input === 'string' ? input : input.url;
                const method = init?.method?.toUpperCase() || 'GET';

                // Apenas interceptar URLs do ERP
                if (!self._shouldIntercept(url)) {
                    return self._originalFetch(input, init);
                }

                return self._interceptedFetch(url, method, init);
            };

            Logger.log('INTERCEPT', 'Fetch interceptor ativo — Chamadas transparentes');
        },

        /**
         * Determina se uma URL deve ser interceptada
         */
        _shouldIntercept(url) {
            // Não interceptar o heartbeat nem o sync endpoint
            if (url.includes('api_sync.php')) return false;
            // Não interceptar login/logout
            if (url.includes('login.php') || url.includes('logout.php')) return false;

            // Interceptar vendas, pré-vendas e rotas relacionadas
            return url.includes('vendas.php') || 
                   url.includes('pre_vendas.php');
        },

        /**
         * Fetch interceptado — 3 etapas de fallback:
         * 1. Tenta com timeout (15s) via rede
         * 2. Se timeout, tenta sem timeout (última chance na rede)
         * 3. Se rede falhar totalmente, usa dados offline do IndexedDB
         */
        async _interceptedFetch(url, method, init) {
            // Se está online, tenta normalmente
            if (ConnectionMonitor.isOnline) {
                try {
                    const response = await this._fetchWithTimeout(url, init, CONFIG.FETCH_TIMEOUT);
                    
                    // Cache incremental de resultados de busca (em background)
                    if (method === 'GET' && url.includes('action=search') && !url.includes('search_clients')) {
                        response.clone().json().then(data => {
                            if (Array.isArray(data)) {
                                CacheManager.cacheSearchResults(data);
                            }
                        }).catch(() => {});
                    }

                    return response;
                } catch (err) {
                    Logger.warn('INTERCEPT', `Fetch com timeout falhou para ${url}: ${err.message} — Tentando sem timeout...`);
                    
                    // SEGUNDA CHANCE: tenta sem timeout
                    try {
                        const retryResponse = await this._originalFetch(url, init || {});
                        
                        if (method === 'GET' && url.includes('action=search') && !url.includes('search_clients')) {
                            retryResponse.clone().json().then(data => {
                                if (Array.isArray(data)) {
                                    CacheManager.cacheSearchResults(data);
                                }
                            }).catch(() => {});
                        }
                        
                        return retryResponse;
                    } catch (retryErr) {
                        Logger.warn('INTERCEPT', `Retry também falhou: ${retryErr.message} — Usando modo offline`);
                        return this._handleOffline(url, method, init);
                    }
                }
            }

            // Modo offline
            return this._handleOffline(url, method, init);
        },

        /**
         * Trata requisição quando offline
         */
        async _handleOffline(url, method, init) {
            const urlObj = new URL(url, window.location.origin);
            const action = urlObj.searchParams.get('action') || 'index';

            Logger.log('INTERCEPT', `Modo offline: ${method} ${action}`);

            // === GET REQUESTS ===
            if (method === 'GET') {
                return this._handleOfflineGET(action, urlObj);
            }

            // === POST REQUESTS ===
            if (method === 'POST') {
                return this._handleOfflinePOST(url, action, init);
            }

            // Fallback
            return this._jsonResponse({ error: 'offline', message: 'Operação não disponível offline' }, 503);
        },

        /**
         * Trata GET requests offline usando dados cacheados
         */
        async _handleOfflineGET(action, urlObj) {
            switch (action) {
                case 'search': {
                    const term = urlObj.searchParams.get('term') || '';
                    if (term.length < 2) return this._jsonResponse([]);

                    // Busca produtos locais
                    const products = await OfflineDB.searchProducts(term);
                    
                    // Também busca pré-vendas offline
                    const presales = await OfflineDB.searchPreSales(term);
                    const pvResults = presales.map(pv => ({
                        id: pv.id,
                        nome: `PRÉ-VENDA: ${pv.codigo} (${pv.cliente_nome || 'Consumidor'})`,
                        preco_venda: pv.valor_total,
                        unidade: 'UN',
                        imagens: '',
                        codigo: pv.codigo,
                        type: 'pre_sale',
                        _offline: true
                    }));

                    const results = [...pvResults, ...products].slice(0, 15);
                    Logger.log('INTERCEPT', `Busca offline '${term}': ${results.length} resultados (${products.length} produtos, ${pvResults.length} PVs)`);
                    return this._jsonResponse(results);
                }

                case 'search_clients': {
                    const term = urlObj.searchParams.get('term') || '';
                    if (term.length < 2) return this._jsonResponse([]);

                    const clients = await OfflineDB.searchClients(term);
                    Logger.log('INTERCEPT', `Busca clientes offline '${term}': ${clients.length} resultados`);
                    return this._jsonResponse(clients);
                }

                case 'list_pending': {
                    const term = urlObj.searchParams.get('term') || '';
                    const presales = await OfflineDB.searchPreSales(term);
                    Logger.log('INTERCEPT', `Lista PVs offline: ${presales.length} resultados`);
                    return this._jsonResponse(presales);
                }

                case 'get_by_code': {
                    const code = urlObj.searchParams.get('code') || '';
                    const pv = await OfflineDB.findPreSaleByCode(code);
                    Logger.log('INTERCEPT', `Busca PV por código '${code}' offline: ${pv ? 'encontrada' : 'não encontrada'}`);
                    return this._jsonResponse(pv);
                }

                case 'list_recent': {
                    // Retorna lista vazia — histórico não é crítico offline
                    return this._jsonResponse({
                        sales: [],
                        total: 0,
                        page: 1,
                        perPage: 4,
                        totalPages: 0
                    });
                }

                case 'list_admins': {
                    // Retornar admin mock para não bloquear descontos
                    return this._jsonResponse([{
                        id: 0,
                        nome: 'Autorização Offline',
                        auth_type: 'pin'
                    }]);
                }

                case 'check_client_completeness': {
                    // No offline, assume que está completo para não bloquear venda fiado
                    return this._jsonResponse({ is_complete: true, missing: [] });
                }

                default:
                    Logger.warn('INTERCEPT', `Ação GET não tratada offline: ${action}`);
                    return this._jsonResponse({ error: 'offline', message: `Ação '${action}' não disponível offline` }, 503);
            }
        },

        /**
         * Trata POST requests offline — enfileira operações
         */
        async _handleOfflinePOST(url, action, init) {
            const body = init?.body ? JSON.parse(init.body) : {};

            switch (action) {
                case 'checkout': {
                    Logger.log('INTERCEPT', '📋 Venda interceptada offline — Enfileirando');
                    const result = await OfflineQueue.enqueueSale(body);
                    return this._jsonResponse(result);
                }

                case 'save': {
                    // Pode ser pre_vendas.php?action=save
                    if (url.includes('pre_vendas.php')) {
                        Logger.log('INTERCEPT', '📋 Pré-Venda interceptada offline — Enfileirando');
                        const result = await OfflineQueue.enqueuePreSale(body);
                        return this._jsonResponse(result);
                    }
                    break;
                }

                case 'authorize_discount': {
                    // Offline: aceita qualquer autorização (confiança no operador)
                    Logger.warn('INTERCEPT', '🔓 Desconto autorizado offline (sem validação de servidor)');
                    return this._jsonResponse({ success: true, offline: true });
                }

                case 'quick_register_client': {
                    // Salva cliente no cache local
                    const tempClientId = 'CLI-' + Date.now().toString(36).toUpperCase();
                    const clientData = {
                        id: tempClientId,
                        nome: body.nome,
                        cpf_cnpj: body.cpf_cnpj || '',
                        doc: body.cpf_cnpj || '',
                        telefone: body.telefone || '',
                        _offline: true
                    };
                    await OfflineDB.put('cached_clients', clientData);
                    Logger.log('INTERCEPT', `Cliente registrado offline: ${body.nome} (${tempClientId})`);
                    return this._jsonResponse({ success: true, client_id: tempClientId, offline: true });
                }

                case 'update_client_quick': {
                    // Offline: aceita a atualização
                    Logger.log('INTERCEPT', 'Atualização de cliente aceita offline');
                    return this._jsonResponse({ success: true, offline: true });
                }
            }

            Logger.warn('INTERCEPT', `Ação POST não tratada offline: ${action}`);
            return this._jsonResponse({ 
                success: false, 
                error: `Operação '${action}' não disponível no modo offline. Aguarde a conexão ser restaurada.` 
            }, 503);
        },

        /**
         * Cria Response JSON (wrapper helper)
         */
        _jsonResponse(data, status = 200) {
            return new Response(JSON.stringify(data), {
                status,
                headers: {
                    'Content-Type': 'application/json',
                    'X-ERP-Offline': 'true'
                }
            });
        },

        /**
         * Fetch com timeout
         */
        _fetchWithTimeout(url, init, timeout) {
            return new Promise((resolve, reject) => {
                const controller = new AbortController();
                const timer = setTimeout(() => {
                    controller.abort();
                    reject(new Error('Timeout'));
                }, timeout);

                this._originalFetch(url, { ...init, signal: controller.signal })
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
    };

    // =========================================================================
    // SESSION BRIDGE — Injeta dados da sessão PHP no contexto JS
    // =========================================================================
    const SessionBridge = {
        async init() {
            // Os dados de sessão são injetados pelo PHP via window.__ERP_SESSION
            if (window.__ERP_SESSION) {
                await OfflineDB.put('session_data', { 
                    key: 'current_session', 
                    ...window.__ERP_SESSION,
                    saved_at: new Date().toISOString()
                });
                Logger.log('SESSION', `Sessão salva: ${window.__ERP_SESSION.usuario_nome} (Filial ${window.__ERP_SESSION.filial_id})`);
            } else {
                // Tentar carregar sessão anterior do IndexedDB
                const saved = await OfflineDB.get('session_data', 'current_session');
                if (saved) {
                    window.__ERP_SESSION = saved;
                    Logger.log('SESSION', `Sessão carregada do cache: ${saved.usuario_nome}`);
                } else {
                    Logger.warn('SESSION', 'Nenhuma sessão disponível — operações offline podem falhar');
                }
            }
        }
    };

    // =========================================================================
    // INICIALIZAÇÃO — Orquestrador principal
    // =========================================================================
    async function init() {
        try {
            // 1. Inicializar IndexedDB
            await OfflineDB.getDB();

            // 2. Salvar/carregar sessão
            await SessionBridge.init();

            // 3. Iniciar monitor de conexão
            ConnectionMonitor.init();

            // 4. Interceptar fetch (ANTES de qualquer outra coisa)
            FetchInterceptor.init();

            // 5. Notificações visuais
            NotificationManager.init();

            // 6. Iniciar cache manager
            await CacheManager.init();

            // 7. Iniciar sync manager
            SyncManager.init();

            // 8. Verificar se há operações pendentes
            const pending = await OfflineDB.getAll('offline_queue');
            const pendingCount = pending.filter(p => p.status === 'pending').length;
            if (pendingCount > 0) {
                Logger.log('INIT', `${pendingCount} operações pendentes na fila`);
                NotificationManager.updatePendingCount(pendingCount);
                
                // Se estiver online, sincronizar imediatamente
                if (ConnectionMonitor.isOnline) {
                    setTimeout(() => SyncManager.processQueue(), 3000);
                }
            }

            Logger.log('INIT', '✅ ERP Offline Bridge inicializado com sucesso');
            Logger.log('INIT', `Modo: ${ConnectionMonitor.isOnline ? '🟢 ONLINE' : '🔴 OFFLINE'}`);

        } catch (err) {
            Logger.error('INIT', '❌ Falha ao inicializar Offline Bridge:', err);
            console.error('[ERP-OFFLINE] ERRO FATAL na inicialização:', err);
        }
    }

    // Expor API para debug (console do dev pode usar)
    window.__ERP_OFFLINE = {
        getQueueStatus: async () => {
            const all = await OfflineDB.getAll('offline_queue');
            return {
                total: all.length,
                pending: all.filter(o => o.status === 'pending').length,
                error: all.filter(o => o.status === 'error').length,
                items: all
            };
        },
        getLogs: async (limit = 50) => {
            const all = await OfflineDB.getAll('sync_log');
            return all.slice(-limit);
        },
        getProductCount: () => OfflineDB.count('cached_products'),
        getClientCount: () => OfflineDB.count('cached_clients'),
        forceSync: () => SyncManager.processQueue(),
        forceCache: () => CacheManager.refreshAll(),
        isOnline: () => ConnectionMonitor.isOnline,
        clearQueue: async () => {
            await OfflineDB.clear('offline_queue');
            NotificationManager.updatePendingCount(0);
            Logger.log('DEBUG', 'Fila offline limpa manualmente');
        },
        clearLogs: () => OfflineDB.clear('sync_log'),
        version: '1.0.0'
    };

    // Iniciar após DOM pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
