/**
 * =============================================================================
 * ERP Elétrica — Offline Bridge v2 (Modo Híbrido)
 * =============================================================================
 * 
 * PRINCÍPIO FUNDAMENTAL:
 *   Quando ONLINE  → 100% pass-through, ZERO interferência nos fetch()
 *   Quando OFFLINE → Intercepta e usa IndexedDB como fallback
 * 
 * Este módulo NÃO altera nenhum comportamento quando a internet está funcionando.
 * Ele só entra em ação quando detecta que a rede caiu.
 * 
 * @version 2.0.0
 * =============================================================================
 */

(function() {
    'use strict';

    // =========================================================================
    // CONFIGURAÇÃO
    // =========================================================================
    const CONFIG = {
        DB_NAME: 'erp_eletrica_offline',
        DB_VERSION: 2,
        HEARTBEAT_INTERVAL: 15000,
        CACHE_REFRESH_INTERVAL: 300000,
        SYNC_INTERVAL: 10000,
        SYNC_ENDPOINT: 'api_sync.php',
        HEARTBEAT_ENDPOINT: 'api_sync.php?action=heartbeat',
        CACHE_PRODUCTS_ENDPOINT: 'api_sync.php?action=cache_products',
        CACHE_CLIENTS_ENDPOINT: 'api_sync.php?action=cache_clients',
    };

    // Referência PURA do fetch — salva ANTES de qualquer override
    const _rawFetch = window.fetch.bind(window);

    // =========================================================================
    // Detectar se estamos numa página relevante (PDV ou Pré-Venda)
    // Se NÃO estamos, o bridge NÃO inicializa (zero overhead em outras páginas)
    // =========================================================================
    const currentPath = window.location.pathname + window.location.search;
    const isRelevantPage = currentPath.includes('vendas.php') || 
                           currentPath.includes('pre_vendas.php') ||
                           currentPath.includes('caixa.php');

    if (!isRelevantPage) {
        // Página não relevante — NÃO inicializa nada
        // Apenas inicia o cache em background silenciosamente
        console.log('[ERP-OFFLINE] Página não crítica — bridge inativo');
        
        // Mesmo em páginas não relevantes, popular o cache silenciosamente
        if (navigator.onLine) {
            setTimeout(() => {
                _rawFetch(CONFIG.CACHE_PRODUCTS_ENDPOINT, { cache: 'no-store' }).catch(() => {});
                _rawFetch(CONFIG.CACHE_CLIENTS_ENDPOINT, { cache: 'no-store' }).catch(() => {});
            }, 5000);
        }
        return;
    }

    console.log('[ERP-OFFLINE] Página crítica detectada — inicializando bridge');

    // =========================================================================
    // LOGGER
    // =========================================================================
    const Logger = {
        log(cat, msg, data) {
            console.log(`[ERP-OFFLINE][${cat}] ${msg}`, data || '');
            this._save('INFO', cat, msg, data);
        },
        warn(cat, msg, data) {
            console.warn(`[ERP-OFFLINE][${cat}] ⚠️ ${msg}`, data || '');
            this._save('WARN', cat, msg, data);
        },
        error(cat, msg, data) {
            console.error(`[ERP-OFFLINE][${cat}] ❌ ${msg}`, data || '');
            this._save('ERROR', cat, msg, data);
        },
        async _save(level, category, message, data) {
            try {
                const db = await OfflineDB.getDB();
                const tx = db.transaction('sync_log', 'readwrite');
                tx.objectStore('sync_log').add({
                    timestamp: new Date().toISOString(),
                    level, category, message,
                    data: data ? JSON.stringify(data) : null
                });
            } catch (e) { /* silêncio */ }
        }
    };

    // =========================================================================
    // IndexedDB MANAGER
    // =========================================================================
    const OfflineDB = {
        _db: null,
        _ready: null,

        getDB() {
            if (this._db) return Promise.resolve(this._db);
            if (this._ready) return this._ready;

            this._ready = new Promise((resolve, reject) => {
                const req = indexedDB.open(CONFIG.DB_NAME, CONFIG.DB_VERSION);

                req.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    
                    // Limpar stores antigos se existirem (upgrade de v1 para v2)
                    const storeNames = Array.from(db.objectStoreNames);
                    storeNames.forEach(name => db.deleteObjectStore(name));

                    const q = db.createObjectStore('offline_queue', { keyPath: 'id', autoIncrement: true });
                    q.createIndex('type', 'type');
                    q.createIndex('status', 'status');

                    const p = db.createObjectStore('cached_products', { keyPath: 'id' });
                    p.createIndex('nome', 'nome');

                    const c = db.createObjectStore('cached_clients', { keyPath: 'id' });
                    c.createIndex('nome', 'nome');

                    const pv = db.createObjectStore('cached_presales', { keyPath: 'id' });
                    pv.createIndex('codigo', 'codigo');

                    db.createObjectStore('sync_log', { keyPath: 'id', autoIncrement: true });
                    db.createObjectStore('session_data', { keyPath: 'key' });
                };

                req.onsuccess = (e) => { this._db = e.target.result; resolve(this._db); };
                req.onerror = (e) => reject(e.target.error);
            });
            return this._ready;
        },

        async put(store, data) {
            const db = await this.getDB();
            return new Promise((res, rej) => {
                const r = db.transaction(store, 'readwrite').objectStore(store).put(data);
                r.onsuccess = () => res(r.result);
                r.onerror = () => rej(r.error);
            });
        },

        async add(store, data) {
            const db = await this.getDB();
            return new Promise((res, rej) => {
                const r = db.transaction(store, 'readwrite').objectStore(store).add(data);
                r.onsuccess = () => res(r.result);
                r.onerror = () => rej(r.error);
            });
        },

        async get(store, key) {
            const db = await this.getDB();
            return new Promise((res, rej) => {
                const r = db.transaction(store, 'readonly').objectStore(store).get(key);
                r.onsuccess = () => res(r.result);
                r.onerror = () => rej(r.error);
            });
        },

        async getAll(store) {
            const db = await this.getDB();
            return new Promise((res, rej) => {
                const r = db.transaction(store, 'readonly').objectStore(store).getAll();
                r.onsuccess = () => res(r.result);
                r.onerror = () => rej(r.error);
            });
        },

        async delete(store, key) {
            const db = await this.getDB();
            return new Promise((res, rej) => {
                const r = db.transaction(store, 'readwrite').objectStore(store).delete(key);
                r.onsuccess = () => res();
                r.onerror = () => rej(r.error);
            });
        },

        async clear(store) {
            const db = await this.getDB();
            return new Promise((res, rej) => {
                const r = db.transaction(store, 'readwrite').objectStore(store).clear();
                r.onsuccess = () => res();
                r.onerror = () => rej(r.error);
            });
        },

        async count(store) {
            const db = await this.getDB();
            return new Promise((res, rej) => {
                const r = db.transaction(store, 'readonly').objectStore(store).count();
                r.onsuccess = () => res(r.result);
                r.onerror = () => rej(r.error);
            });
        },

        async searchProducts(term) {
            const all = await this.getAll('cached_products');
            const t = term.toLowerCase().trim();
            return all
                .filter(p => {
                    const nome = (p.nome || '').toLowerCase();
                    const codigo = (p.codigo || '').toLowerCase();
                    return nome.includes(t) || codigo.includes(t) || String(p.id) === t;
                })
                .slice(0, 15)
                .map(p => ({
                    id: p.id, nome: p.nome, preco_venda: p.preco_venda,
                    unidade: p.unidade || 'UN', imagens: p.imagens || '',
                    codigo: p.codigo || '', type: 'product',
                    stock_qty: p.stock_qty || 0, _offline: true
                }));
        },

        async searchClients(term) {
            const all = await this.getAll('cached_clients');
            const t = term.toLowerCase().trim();
            return all
                .filter(c => {
                    const nome = (c.nome || '').toLowerCase();
                    const doc = (c.cpf_cnpj || '').toLowerCase();
                    return nome.includes(t) || doc.includes(t);
                })
                .slice(0, 10)
                .map(c => ({ id: c.id, nome: c.nome, doc: c.cpf_cnpj || '' }));
        },

        async getPreSales(term) {
            const cached = await this.getAll('cached_presales');
            const queue = await this.getAll('offline_queue');
            const offlinePVs = queue
                .filter(q => q.type === 'presale' && q.status === 'pending')
                .map(q => ({
                    id: q.temp_id,
                    codigo: q.temp_code || q.data.codigo,
                    valor_total: q.data.valor_total,
                    status: 'pendente',
                    cliente_nome: q.data.nome_cliente_avulso || 'Consumidor',
                    vendedor_nome: q.session?.usuario_nome || '',
                    _offline: true
                }));

            let results = [...offlinePVs, ...cached];
            if (term) {
                const t = term.toLowerCase().trim();
                results = results.filter(pv =>
                    (pv.codigo || '').toLowerCase().includes(t) ||
                    (pv.cliente_nome || '').toLowerCase().includes(t)
                );
            } else {
                results = results.filter(pv => pv.status === 'pendente');
            }
            return results.slice(0, 30);
        },

        async findPreSaleByCode(code) {
            const queue = await this.getAll('offline_queue');
            const offlinePV = queue.find(
                q => q.type === 'presale' && q.status === 'pending' &&
                     (q.data.codigo === code || q.temp_code === code)
            );
            if (offlinePV) {
                return {
                    id: offlinePV.temp_id,
                    codigo: offlinePV.temp_code || offlinePV.data.codigo,
                    valor_total: offlinePV.data.valor_total,
                    status: 'pendente',
                    cliente_id: offlinePV.data.cliente_id,
                    cliente_nome: offlinePV.data.nome_cliente_avulso || 'Consumidor',
                    nome_cliente_avulso: offlinePV.data.nome_cliente_avulso,
                    cliente_doc: offlinePV.data.cpf_cliente,
                    itens: (offlinePV.data.items || []).map(i => ({
                        produto_id: i.id, produto_nome: i.nome,
                        preco_unitario: i.price, quantidade: i.qty,
                        imagens: i.imagens || ''
                    })),
                    _offline: true
                };
            }
            const cached = await this.getAll('cached_presales');
            return cached.find(pv => pv.codigo === code && pv.status === 'pendente') || null;
        }
    };

    // =========================================================================
    // CONNECTION MONITOR
    // =========================================================================
    const ConnectionMonitor = {
        _isOnline: navigator.onLine,
        _listeners: [],
        _failures: 0,

        get isOnline() { return this._isOnline; },

        init() {
            window.addEventListener('online', () => this._setOnline());
            window.addEventListener('offline', () => this._setOffline());

            // Heartbeat com delay para não atrapalhar carregamento
            setTimeout(() => {
                this._heartbeat();
                setInterval(() => this._heartbeat(), CONFIG.HEARTBEAT_INTERVAL);
            }, 5000);
        },

        onChange(cb) { this._listeners.push(cb); },

        async _heartbeat() {
            try {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 8000);
                const res = await _rawFetch(CONFIG.HEARTBEAT_ENDPOINT, {
                    method: 'GET', cache: 'no-store', signal: ctrl.signal
                });
                clearTimeout(timer);
                if (res.ok) { this._failures = 0; if (!this._isOnline) this._setOnline(); }
                else this._fail();
            } catch (e) { this._fail(); }
        },

        _fail() {
            this._failures++;
            if (this._failures >= 3 && this._isOnline) this._setOffline();
        },

        _setOnline() {
            if (this._isOnline) return;
            this._isOnline = true;
            this._failures = 0;
            Logger.log('CONN', '🟢 Conexão RESTAURADA');
            this._listeners.forEach(cb => { try { cb('online'); } catch(e){} });
        },

        _setOffline() {
            if (!this._isOnline) return;
            this._isOnline = false;
            Logger.warn('CONN', '🔴 Conexão PERDIDA — Modo offline ativado');
            this._listeners.forEach(cb => { try { cb('offline'); } catch(e){} });
        }
    };

    // =========================================================================
    // NOTIFICATION MANAGER — Indicador visual discreto
    // =========================================================================
346:     // =========================================================================
347:     // NOTIFICATION MANAGER — Indicador visual e Avisos Detalhados
348:     // =========================================================================
349:     const NotificationUI = {
350:         _el: null,
351: 
352:         init() {
353:             const el = document.createElement('div');
354:             el.id = 'erp-offline-indicator';
355:             el.style.cssText = `
356:                 position:fixed; bottom:20px; right:20px; z-index:99999;
357:                 padding:12px 20px; border-radius:12px; font-size:13px;
358:                 font-weight:600; font-family:'Inter',sans-serif;
359:                 display:flex; flex-direction:column; gap:8px;
360:                 transition:all .4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
361:                 pointer-events:auto; opacity:0; transform:translateY(20px);
362:                 box-shadow:0 10px 25px rgba(0,0,0,0.2); max-width: 320px;
363:             `;
364:             document.body.appendChild(el);
365:             this._el = el;
366: 
367:             ConnectionMonitor.onChange(status => {
368:                 if (status === 'online') {
369:                     this._show('#10b981', '🟢 Conexão Estabelecida', 'O sistema está online.');
370:                     setTimeout(() => this._hide(), 4000);
371:                 } else {
372:                     this._show('#ef4444', '🔴 Modo Offline Ativado', 'Vendas serão salvas localmente.');
373:                 }
374:             });
375:         },
376: 
377:         _show(bg, title, text) {
378:             if (!this._el) return;
379:             this._el.style.background = bg;
380:             this._el.style.color = '#fff';
381:             this._el.innerHTML = `
382:                 <div style="display:flex; align-items:center; gap:8px;">
383:                     <strong>${title}</strong>
384:                 </div>
385:                 <div style="font-size:11px; opacity:0.9; font-weight:400;">${text}</div>
386:             `;
387:             this._el.style.opacity = '1';
388:             this._el.style.transform = 'translateY(0)';
389:         },
390: 
391:         _hide() {
392:             if (!this._el) return;
393:             this._el.style.opacity = '0';
394:             this._el.style.transform = 'translateY(20px)';
395:         },
396: 
397:         showSyncProgress(count) {
398:             this._show('#3b82f6', '🔄 Sincronizando', `Enviando ${count} operação(ões) pendente(s)...`);
399:         },
400: 
401:         showSyncResult(results) {
402:             const ok = results.filter(r => r.success);
403:             if (ok.length === 0) return;
404: 
405:             let details = ok.map(r => {
406:                 const icon = r.type === 'sale' ? '💰' : '📝';
407:                 const label = r.type === 'sale' ? 'Venda' : 'P-Venda';
408:                 const valor = r.total ? ` (R$ ${parseFloat(r.total).toFixed(2)})` : '';
409:                 return `<div>${icon} ${label} #${r.real_id || r.real_code}${valor}</div>`;
410:             }).join('');
411: 
412:             this._show('#10b981', '✅ Sincronizado com Sucesso', `
413:                 <div style="margin-bottom:5px;">${ok.length} operação(ões) enviada(s):</div>
414:                 <div style="max-height:100px; overflow-y:auto; font-size:10px; background:rgba(0,0,0,0.1); padding:5px; border-radius:4px;">
415:                     ${details}
416:                 </div>
417:             `);
418:             
419:             // Som de sucesso (opcional, discreto)
420:             try { 
421:                 const ctx = new (window.AudioContext || window.webkitAudioContext)();
422:                 const osc = ctx.createOscillator();
423:                 const gain = ctx.createGain();
424:                 osc.connect(gain); gain.connect(ctx.destination);
425:                 osc.type = 'sine'; osc.frequency.setValueAtTime(880, ctx.currentTime);
426:                 gain.gain.setValueAtTime(0.1, ctx.currentTime);
427:                 osc.start(); osc.stop(ctx.currentTime + 0.1);
428:             } catch(e){}
429: 
430:             setTimeout(() => this._hide(), 8000);
431:         },
432: 
433:         showPending(count) {
434:             if (count > 0 && !ConnectionMonitor.isOnline) {
435:                 this._show('#ef4444', '🔴 Modo Offline', `${count} venda(s) aguardando conexão.`);
436:             }
437:         }
438:     };
439: 
440:     // =========================================================================
441:     // CACHE MANAGER
442:     // =========================================================================
443:     const CacheManager = {
444:         async init() {
445:             if (ConnectionMonitor.isOnline) {
446:                 this.refreshAll().catch(() => {});
447:             }
448:             setInterval(() => {
449:                 if (ConnectionMonitor.isOnline) this.refreshAll().catch(() => {});
450:             }, CONFIG.CACHE_REFRESH_INTERVAL);
451:         },
445: 
446:         async refreshAll() {
447:             await Promise.allSettled([
448:                 this._cacheEndpoint(CONFIG.CACHE_PRODUCTS_ENDPOINT, 'cached_products'),
449:                 this._cacheEndpoint(CONFIG.CACHE_CLIENTS_ENDPOINT, 'cached_clients'),
450:                 this._cachePreSales()
451:             ]);
452:         },
453: 
454:         async _cacheEndpoint(url, storeName) {
455:             try {
456:                 const res = await _rawFetch(url, { cache: 'no-store' });
457:                 if (!res.ok) return;
458:                 const data = await res.json();
459:                 if (!Array.isArray(data)) return;
460:                 await OfflineDB.clear(storeName);
461:                 for (const item of data) await OfflineDB.put(storeName, item);
462:                 Logger.log('CACHE', `${storeName}: ${data.length} itens cacheados`);
463:             } catch (e) {
464:                 Logger.warn('CACHE', `Falha ao cachear ${storeName}`, e);
465:             }
466:         },
467: 
468:         async _cachePreSales() {
469:             try {
470:                 const res = await _rawFetch('pre_vendas.php?action=list_pending', { cache: 'no-store' });
471:                 if (!res.ok) return;
472:                 const data = await res.json();
473:                 if (!Array.isArray(data)) return;
474:                 await OfflineDB.clear('cached_presales');
475:                 for (const pv of data) await OfflineDB.put('cached_presales', pv);
476:                 Logger.log('CACHE', `Pré-vendas: ${data.length} itens cacheados`);
477:             } catch (e) {
478:                 Logger.warn('CACHE', 'Falha ao cachear pré-vendas', e);
479:             }
480:         },
481: 
482:         async cacheSearchResults(products) {
483:             try {
484:                 for (const p of products) {
485:                     if (p.type !== 'pre_sale' && p.id) {
486:                         await OfflineDB.put('cached_products', p);
487:                     }
488:                 }
489:             } catch (e) { /* silêncio */ }
490:         }
491:     };
492: 
493:     // =========================================================================
494:     // SYNC MANAGER
495:     // =========================================================================
496:     const SyncManager = {
497:         _busy: false,
498: 
499:         init() {
500:             ConnectionMonitor.onChange(status => {
501:                 if (status === 'online') setTimeout(() => this.sync(), 2000);
502:             });
503:             setInterval(() => {
504:                 if (ConnectionMonitor.isOnline && !this._busy) this.sync();
505:             }, CONFIG.SYNC_INTERVAL);
506:         },
507: 
508:         async sync() {
509:             if (this._busy || !ConnectionMonitor.isOnline) return;
510: 
511:             const all = await OfflineDB.getAll('offline_queue');
512:             const pending = all.filter(o => o.status === 'pending');
513:             if (pending.length === 0) return;
514: 
515:             this._busy = true;
516:             Logger.log('SYNC', `Iniciando sincronização de ${pending.length} itens`);
517:             NotificationUI.showSyncProgress(pending.length);
518: 
519:             pending.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
520: 
521:             const syncResults = [];
522: 
523:             for (const op of pending) {
524:                 try {
525:                     const res = await _rawFetch(CONFIG.SYNC_ENDPOINT, {
526:                         method: 'POST',
527:                         headers: { 'Content-Type': 'application/json' },
528:                         body: JSON.stringify({
529:                             action: 'sync_batch',
530:                             operations: [{
531:                                 type: op.type, temp_id: op.temp_id,
532:                                 temp_code: op.temp_code || null,
533:                                 data: op.data, session: op.session,
534:                                 created_at: op.created_at,
535:                                 is_contingencia: op.is_contingencia || false
536:                             }]
537:                         })
538:                     });
539:                     const result = await res.json();
540:                     if (result.success && result.results?.[0]?.success) {
541:                         await OfflineDB.delete('offline_queue', op.id);
542:                         const serverOp = result.results[0];
543:                         syncResults.push({
544:                             success: true,
545:                             type: op.type,
546:                             real_id: serverOp.real_id,
547:                             real_code: serverOp.real_code,
548:                             total: op.data.total || op.data.valor_total
549:                         });
550:                         Logger.log('SYNC', `✅ Sincronizado: ${op.temp_id}`);
551:                     } else {
552:                         throw new Error(result.error || result.results?.[0]?.error || 'Erro no servidor');
553:                     }
554:                 } catch (err) {
555:                     op.retry_count = (op.retry_count || 0) + 1;
556:                     if (op.retry_count >= 5) {
557:                         op.status = 'error';
558:                         op.error_message = err.message;
559:                     }
560:                     await OfflineDB.put('offline_queue', op);
561:                     Logger.error('SYNC', `❌ Erro ao sincronizar ${op.temp_id}: ${err.message}`);
562:                 }
563:             }
564: 
565:             this._busy = false;
566:             if (syncResults.length > 0) {
567:                 NotificationUI.showSyncResult(syncResults);
568:                 CacheManager.refreshAll().catch(() => {});
569:             } else {
570:                 const remaining = (await OfflineDB.getAll('offline_queue')).filter(o => o.status === 'pending');
571:                 if (remaining.length === 0) NotificationUI._hide();
572:                 else NotificationUI.showPending(remaining.length);
573:             }
574:         }
575:     };


    // =========================================================================
    // OFFLINE QUEUE — Enfileira operações
    // =========================================================================
    const OfflineQueue = {
        _counter: 0,

        _tempId() {
            return 'OFF-' + Date.now().toString(36).toUpperCase() + '-' +
                   (++this._counter).toString(36).toUpperCase();
        },

        _tempPVCode() {
            return 'PV-OFF-' + Date.now().toString(36).toUpperCase().substr(-5);
        },

        _session() {
            return {
                usuario_id: window.__ERP_SESSION?.usuario_id || null,
                usuario_nome: window.__ERP_SESSION?.usuario_nome || 'Desconhecido',
                filial_id: window.__ERP_SESSION?.filial_id || 1,
                is_matriz: window.__ERP_SESSION?.is_matriz || false,
                usuario_nivel: window.__ERP_SESSION?.usuario_nivel || 'operador'
            };
        },

        async enqueueSale(data) {
            const tempId = this._tempId();
            const isContingencia = data.tipo_nota === 'fiscal';
            if (isContingencia) data.tipo_nota = 'contingencia';

            await OfflineDB.add('offline_queue', {
                type: 'sale', temp_id: tempId, status: 'pending',
                data, session: this._session(),
                created_at: new Date().toISOString(),
                retry_count: 0, is_contingencia: isContingencia
            });

            Logger.log('QUEUE', `Venda enfileirada: ${tempId}, R$${data.total}`);
            const count = (await OfflineDB.getAll('offline_queue')).filter(o => o.status === 'pending').length;
            NotificationUI.showPending(count);

            return { success: true, sale_id: tempId, tipo_nota: data.tipo_nota, offline: true, contingencia: isContingencia };
        },

        async enqueuePreSale(data) {
            const tempId = this._tempId();
            const tempCode = this._tempPVCode();
            data.codigo = tempCode;

            await OfflineDB.add('offline_queue', {
                type: 'presale', temp_id: tempId, temp_code: tempCode,
                status: 'pending', data, session: this._session(),
                created_at: new Date().toISOString(), retry_count: 0
            });

            Logger.log('QUEUE', `Pré-venda enfileirada: ${tempId} (${tempCode})`);
            const count = (await OfflineDB.getAll('offline_queue')).filter(o => o.status === 'pending').length;
            NotificationUI.showPending(count);

            return { success: true, id: tempId, codigo: tempCode, offline: true };
        }
    };

    // =========================================================================
    // FETCH INTERCEPTOR — ZERO interferência quando online
    // =========================================================================
    const FetchInterceptor = {
        init() {
            const self = this;

            window.fetch = function(input, init) {
                const url = typeof input === 'string' ? input : (input?.url || String(input));
                const method = (init?.method || 'GET').toUpperCase();

                // REGRA 1: Só intercepta vendas.php e pre_vendas.php
                if (!url.includes('vendas.php') && !url.includes('pre_vendas.php')) {
                    return _rawFetch(input, init);
                }

                // REGRA 2: Não intercepta api_sync.php
                if (url.includes('api_sync.php')) {
                    return _rawFetch(input, init);
                }

                // REGRA 3: Se ONLINE → 100% pass-through, ZERO modificação
                if (ConnectionMonitor.isOnline) {
                    // Chama o fetch ORIGINAL com os argumentos ORIGINAIS, sem nenhuma alteração
                    const result = _rawFetch(input, init);

                    // Em background: cachear resultados de busca (não afeta a resposta)
                    if (method === 'GET' && url.includes('action=search') && !url.includes('search_clients')) {
                        result.then(res => {
                            res.clone().json().then(data => {
                                if (Array.isArray(data)) CacheManager.cacheSearchResults(data);
                            }).catch(() => {});
                        }).catch(() => {});
                    }

                    return result;
                }

                // REGRA 4: Se OFFLINE → Interceptar
                return self._handleOffline(url, method, init);
            };

            Logger.log('INTERCEPT', 'Interceptor ativo (pass-through quando online)');
        },

        async _handleOffline(url, method, init) {
            const urlObj = new URL(url, window.location.origin);
            const action = urlObj.searchParams.get('action') || 'index';

            Logger.log('INTERCEPT', `OFFLINE: ${method} ${action}`);

            if (method === 'GET') return this._offlineGET(action, urlObj);
            if (method === 'POST') return this._offlinePOST(url, action, init);

            return this._json({ error: 'offline' }, 503);
        },

        async _offlineGET(action, urlObj) {
            switch (action) {
                case 'search': {
                    const term = urlObj.searchParams.get('term') || '';
                    if (term.length < 2) return this._json([]);
                    const products = await OfflineDB.searchProducts(term);
                    const pvs = await OfflineDB.getPreSales(term);
                    const pvResults = pvs.map(pv => ({
                        id: pv.id, nome: `PRÉ-VENDA: ${pv.codigo} (${pv.cliente_nome || 'Consumidor'})`,
                        preco_venda: pv.valor_total, unidade: 'UN', imagens: '',
                        codigo: pv.codigo, type: 'pre_sale', _offline: true
                    }));
                    return this._json([...pvResults, ...products].slice(0, 15));
                }
                case 'search_clients': {
                    const term = urlObj.searchParams.get('term') || '';
                    if (term.length < 2) return this._json([]);
                    return this._json(await OfflineDB.searchClients(term));
                }
                case 'list_pending': {
                    const term = urlObj.searchParams.get('term') || '';
                    return this._json(await OfflineDB.getPreSales(term));
                }
                case 'get_by_code': {
                    const code = urlObj.searchParams.get('code') || '';
                    return this._json(await OfflineDB.findPreSaleByCode(code));
                }
                case 'list_recent':
                    return this._json({ sales: [], total: 0, page: 1, perPage: 4, totalPages: 0 });
                case 'list_admins':
                    return this._json([{ id: 0, nome: 'Autorização Offline', auth_type: 'pin' }]);
                case 'check_client_completeness':
                    return this._json({ is_complete: true, missing: [] });
                default:
                    return this._json({ error: 'offline', message: `'${action}' indisponível offline` }, 503);
            }
        },

        async _offlinePOST(url, action, init) {
            const body = init?.body ? JSON.parse(init.body) : {};

            switch (action) {
                case 'checkout':
                    return this._json(await OfflineQueue.enqueueSale(body));
                case 'save':
                    if (url.includes('pre_vendas.php')) {
                        return this._json(await OfflineQueue.enqueuePreSale(body));
                    }
                    break;
                case 'authorize_discount':
                    Logger.warn('INTERCEPT', 'Desconto autorizado offline');
                    return this._json({ success: true, offline: true });
                case 'quick_register_client': {
                    const tempId = 'CLI-' + Date.now().toString(36).toUpperCase();
                    await OfflineDB.put('cached_clients', {
                        id: tempId, nome: body.nome,
                        cpf_cnpj: body.cpf_cnpj || '', telefone: body.telefone || ''
                    });
                    return this._json({ success: true, client_id: tempId, offline: true });
                }
                case 'update_client_quick':
                    return this._json({ success: true, offline: true });
            }

            return this._json({ success: false, error: `'${action}' indisponível offline` }, 503);
        },

        _json(data, status = 200) {
            return new Response(JSON.stringify(data), {
                status,
                headers: { 'Content-Type': 'application/json', 'X-ERP-Offline': 'true' }
            });
        }
    };

    // =========================================================================
    // SESSION BRIDGE
    // =========================================================================
    const SessionBridge = {
        async init() {
            if (window.__ERP_SESSION) {
                await OfflineDB.put('session_data', {
                    key: 'current', ...window.__ERP_SESSION,
                    saved_at: new Date().toISOString()
                });
            } else {
                const saved = await OfflineDB.get('session_data', 'current');
                if (saved) window.__ERP_SESSION = saved;
            }
        }
    };

    // =========================================================================
    // INICIALIZAÇÃO
    // =========================================================================
    async function init() {
        try {
            await OfflineDB.getDB();
            await SessionBridge.init();
            ConnectionMonitor.init();
            FetchInterceptor.init();
            NotificationUI.init();
            await CacheManager.init();
            SyncManager.init();

            // Verificar pendentes
            const pending = (await OfflineDB.getAll('offline_queue')).filter(o => o.status === 'pending');
            if (pending.length > 0) {
                NotificationUI.showPending(pending.length);
                if (ConnectionMonitor.isOnline) setTimeout(() => SyncManager.sync(), 3000);
            }

            Logger.log('INIT', `✅ Bridge v2 ativo | ${ConnectionMonitor.isOnline ? 'ONLINE' : 'OFFLINE'}`);
        } catch (err) {
            console.error('[ERP-OFFLINE] ERRO na inicialização:', err);
        }
    }

    // Debug API
    window.__ERP_OFFLINE = {
        getQueue: () => OfflineDB.getAll('offline_queue'),
        getLogs: (n = 50) => OfflineDB.getAll('sync_log').then(l => l.slice(-n)),
        productCount: () => OfflineDB.count('cached_products'),
        clientCount: () => OfflineDB.count('cached_clients'),
        forceSync: () => SyncManager.sync(),
        forceCache: () => CacheManager.refreshAll(),
        isOnline: () => ConnectionMonitor.isOnline,
        clearQueue: async () => { await OfflineDB.clear('offline_queue'); NotificationUI._hide(); },
        clearAll: async () => {
            await OfflineDB.clear('offline_queue');
            await OfflineDB.clear('cached_products');
            await OfflineDB.clear('cached_clients');
            await OfflineDB.clear('cached_presales');
            await OfflineDB.clear('sync_log');
            console.log('[ERP-OFFLINE] Tudo limpo');
        },
        version: '2.0.0'
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
