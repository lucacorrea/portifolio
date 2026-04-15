<?php
/**
 * =============================================================================
 * ERP Elétrica — Sync Daemon (Camada 2)
 * =============================================================================
 * 
 * Script que roda CONTINUAMENTE na máquina do Balcão (XAMPP).
 * Sincroniza dados entre o banco LOCAL (MariaDB) e o REMOTO (Hostinger).
 * 
 * COMO RODAR:
 *   php sync_daemon.php
 * 
 * COMO RODAR EM BACKGROUND (Windows):
 *   start /B php sync_daemon.php > storage\logs\daemon_output.log 2>&1
 * 
 * COMO PARAR:
 *   Ctrl+C no terminal, ou fechar a janela do CMD
 * 
 * DICA: Para rodar automaticamente ao ligar o PC, adicione um atalho em:
 *   shell:startup → criar atalho para: php.exe C:\xampp\htdocs\erp_eletrica\sync_daemon.php
 * 
 * @version 1.0.0
 */

// Apenas CLI
if (php_sapi_name() !== 'cli') {
    die("Este script deve ser rodado via linha de comando (CLI).\n");
}

// Carregar configurações
require_once __DIR__ . '/sync_config.php';
require_once __DIR__ . '/autoloader.php';

use App\Services\DatabaseReplicator;

// Banner
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   ERP Elétrica — Sync Daemon v1.0                      ║\n";
echo "║   Sincronização Local ↔ Remoto                         ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

if (!defined('SYNC_ENABLED') || !SYNC_ENABLED) {
    die("[DAEMON] Sincronização desabilitada (SYNC_ENABLED = false). Encerrando.\n");
}

// =========================================================================
// CONEXÕES
// =========================================================================

/** Conectar ao banco REMOTO (Hostinger) */
function connectRemote(): ?\PDO {
    try {
        $dsn = "mysql:host=" . REMOTE_DB_HOST . ";port=" . REMOTE_DB_PORT . ";dbname=" . REMOTE_DB_NAME . ";charset=utf8mb4";
        $pdo = new \PDO($dsn, REMOTE_DB_USER, REMOTE_DB_PASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => 10,
        ]);
        return $pdo;
    } catch (\PDOException $e) {
        logDaemon('ERROR', "Conexão REMOTA falhou: " . $e->getMessage());
        return null;
    }
}

/** Conectar ao banco LOCAL (XAMPP) */
function connectLocal(): ?\PDO {
    try {
        $dsn = "mysql:host=" . LOCAL_DB_HOST . ";port=" . LOCAL_DB_PORT . ";dbname=" . LOCAL_DB_NAME . ";charset=utf8mb4";
        $pdo = new \PDO($dsn, LOCAL_DB_USER, LOCAL_DB_PASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (\PDOException $e) {
        logDaemon('FATAL', "Conexão LOCAL falhou: " . $e->getMessage());
        logDaemon('FATAL', "Verifique se o MariaDB está rodando no XAMPP e se o banco '${\constant('LOCAL_DB_NAME')}' existe.");
        logDaemon('FATAL', "Execute 'php setup_local_db.php' para criar o banco local.");
        return null;
    }
}

// =========================================================================
// LOOP PRINCIPAL
// =========================================================================

$referenceInterval = defined('SYNC_INTERVAL_REFERENCE') ? SYNC_INTERVAL_REFERENCE : 300;
$transactionInterval = defined('SYNC_INTERVAL_TRANSACTION') ? SYNC_INTERVAL_TRANSACTION : 30;

$lastReferenceSync = 0;
$lastTransactionSync = 0;
$cycleCount = 0;

logDaemon('INFO', "Daemon iniciado com sucesso");
logDaemon('INFO', "Intervalo referência: {$referenceInterval}s | Intervalo transações: {$transactionInterval}s");
logDaemon('INFO', "Remoto: " . REMOTE_DB_HOST . "/" . REMOTE_DB_NAME);
logDaemon('INFO', "Local:  " . LOCAL_DB_HOST . "/" . LOCAL_DB_NAME);
logDaemon('INFO', "Pressione Ctrl+C para parar.\n");

// Tratamento de sinal para shutdown gracioso
$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function() use (&$running) {
        echo "\n[DAEMON] Recebido sinal de parada. Finalizando...\n";
        $running = false;
    });
    pcntl_signal(SIGTERM, function() use (&$running) {
        $running = false;
    });
}

while ($running) {
    $now = time();
    $cycleCount++;

    // Verificar se precisa rodar sync
    $doReference = ($now - $lastReferenceSync) >= $referenceInterval;
    $doTransaction = ($now - $lastTransactionSync) >= $transactionInterval;

    if (!$doReference && !$doTransaction) {
        sleep(5);
        if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
        continue;
    }

    // Conectar aos bancos
    $remoteDb = connectRemote();
    $localDb = connectLocal();

    if (!$localDb) {
        logDaemon('ERROR', "Banco local indisponível — aguardando 30s");
        sleep(30);
        continue;
    }

    if (!$remoteDb) {
        logDaemon('WARN', "Banco remoto indisponível — modo offline ativo. Aguardando {$transactionInterval}s");
        $lastTransactionSync = $now; // Evitar spam de tentativas
        sleep($transactionInterval);
        continue;
    }

    // Criar replicador
    $replicator = new DatabaseReplicator($remoteDb, $localDb);

    // ---- SYNC DE REFERÊNCIA (a cada 5 min) ----
    if ($doReference) {
        logDaemon('INFO', "═══ Ciclo #$cycleCount — Sync de Referência ═══");
        
        try {
            $stats = $replicator->syncReferenceData();
            logDaemon('OK', "Referência: {$stats['pulled']} puxados, {$stats['errors']} erros, {$stats['skipped']} pulados");
        } catch (\Exception $e) {
            logDaemon('ERROR', "Falha no sync de referência: " . $e->getMessage());
        }

        $lastReferenceSync = $now;
    }

    // ---- SYNC TRANSACIONAL (a cada 30 seg) ----
    if ($doTransaction) {
        logDaemon('INFO', "── Ciclo #$cycleCount — Sync Transacional ──");

        try {
            $stats = $replicator->syncTransactionalData();
            
            if ($stats['pulled'] > 0 || $stats['pushed'] > 0) {
                logDaemon('OK', "Transações: {$stats['pulled']} puxados, {$stats['pushed']} enviados, {$stats['errors']} erros");
            }
        } catch (\Exception $e) {
            logDaemon('ERROR', "Falha no sync transacional: " . $e->getMessage());
        }

        $lastTransactionSync = $now;
    }

    // Fechar conexões (serão reabertas no próximo ciclo)
    $remoteDb = null;
    $localDb = null;

    // Dispatch sinais se disponível
    if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();

    // Pequena pausa entre ciclos
    sleep(2);
}

logDaemon('INFO', "Daemon encerrado com sucesso.");

// =========================================================================
// LOGGER
// =========================================================================
function logDaemon(string $level, string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    $prefix = match($level) {
        'OK'    => '✅',
        'INFO'  => 'ℹ️',
        'WARN'  => '⚠️',
        'ERROR' => '❌',
        'FATAL' => '💀',
        default => '  ',
    };
    
    $line = "[$timestamp] [$level] $prefix $message\n";
    echo $line;

    // Salvar em arquivo
    $logDir = defined('SYNC_LOG_DIR') ? SYNC_LOG_DIR : __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/sync_daemon_' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}
