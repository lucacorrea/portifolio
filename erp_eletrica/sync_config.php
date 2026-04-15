<?php
/**
 * =============================================================================
 * ERP Elétrica — Configuração do Modo Híbrido (Camada 2)
 * =============================================================================
 * 
 * Este arquivo configura a replicação bidirecional entre o banco
 * REMOTO (Hostinger) e o banco LOCAL (XAMPP MariaDB no Balcão).
 * 
 * INSTRUÇÕES:
 *  1. Na Hostinger: NÃO alterar nada — usa o config.php padrão
 *  2. No XAMPP local: copiar o sistema inteiro para htdocs/ e configurar este arquivo
 *  3. O sync_daemon.php usa este arquivo para saber onde estão os dois bancos
 */

// =============================================================================
// BANCO REMOTO (Hostinger) — Banco master/principal
// =============================================================================
define('REMOTE_DB_HOST', 'srv1819.hstgr.io');
define('REMOTE_DB_PORT', 3306);
define('REMOTE_DB_NAME', 'u784961086_pdv');
define('REMOTE_DB_USER', 'u784961086_pdv');
define('REMOTE_DB_PASS', 'Uv$1NhLlkRub');

// =============================================================================
// BANCO LOCAL (XAMPP MariaDB) — Banco espelho/backup
// =============================================================================
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_PORT', 3306);
define('LOCAL_DB_NAME', 'erp_eletrica_local');
define('LOCAL_DB_USER', 'root');
define('LOCAL_DB_PASS', '');  // XAMPP padrão é sem senha. Altere se configurar senha.

// =============================================================================
// URL DO SERVIDOR LOCAL (para o frontend quando a internet cair)
// =============================================================================
// Coloque o IP da máquina balcão na rede local.
// Exemplo: http://192.168.1.100 ou http://192.168.0.50
// A pré-venda usará esta URL como fallback via LAN quando Hostinger estiver fora.
define('LOCAL_SERVER_URL', 'http://192.168.1.100');

// =============================================================================
// CONFIGURAÇÕES DE SINCRONIZAÇÃO
// =============================================================================
define('SYNC_ENABLED', true);

// Intervalo de sync em segundos
define('SYNC_INTERVAL_REFERENCE', 300);  // 5 min  — Dados de referência (produtos, clientes)
define('SYNC_INTERVAL_TRANSACTION', 30); // 30 seg — Dados transacionais (vendas, pré-vendas)

// Direção do sync quando há conflito (qual banco "ganha")
// 'remote' = Hostinger é master (recomendado)
// 'local'  = Banco local é master
define('SYNC_CONFLICT_WINNER', 'remote');

// =============================================================================
// TABELAS — Quais tabelas sincronizar e em que direção
// =============================================================================
// Formato: 'tabela' => ['direction' => 'remote_to_local|local_to_remote|bidirectional', 'priority' => 'reference|transaction']
define('SYNC_TABLES', [
    // Dados de referência (remote → local) — Apenas leitura no local
    'produtos'          => ['direction' => 'remote_to_local', 'priority' => 'reference'],
    'estoque_filiais'   => ['direction' => 'remote_to_local', 'priority' => 'reference'],
    'clientes'          => ['direction' => 'remote_to_local', 'priority' => 'reference'],
    'usuarios'          => ['direction' => 'remote_to_local', 'priority' => 'reference'],
    'filiais'           => ['direction' => 'remote_to_local', 'priority' => 'reference'],
    'categorias'        => ['direction' => 'remote_to_local', 'priority' => 'reference'],
    'fornecedores'      => ['direction' => 'remote_to_local', 'priority' => 'reference'],

    // Dados transacionais (bidirecional)
    'vendas'            => ['direction' => 'bidirectional', 'priority' => 'transaction'],
    'vendas_itens'      => ['direction' => 'bidirectional', 'priority' => 'transaction'],
    'pre_vendas'        => ['direction' => 'bidirectional', 'priority' => 'transaction'],
    'pre_venda_itens'   => ['direction' => 'bidirectional', 'priority' => 'transaction'],
    'caixas'            => ['direction' => 'bidirectional', 'priority' => 'transaction'],
    'caixa_movimentos'  => ['direction' => 'bidirectional', 'priority' => 'transaction'],
    'contas_receber'    => ['direction' => 'bidirectional', 'priority' => 'transaction'],
    'fiados_pagamentos' => ['direction' => 'bidirectional', 'priority' => 'transaction'],

    // Logs de sync (local → remote) — Apenas escrita do local
    'sync_audit_log'    => ['direction' => 'local_to_remote', 'priority' => 'reference'],
]);

// =============================================================================
// LOGGING
// =============================================================================
define('SYNC_LOG_DIR', __DIR__ . '/storage/logs');
define('SYNC_LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARN, ERROR
