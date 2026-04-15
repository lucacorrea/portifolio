<?php
namespace App\Services;

/**
 * =============================================================================
 * DatabaseReplicator — Motor de Replicação Bidirecional
 * =============================================================================
 * 
 * Sincroniza dados entre o banco REMOTO (Hostinger) e o banco LOCAL (XAMPP).
 * 
 * Estratégias:
 *  - Dados de referência (produtos, clientes): FULL SYNC (remote → local)
 *  - Dados transacionais (vendas, pré-vendas): INCREMENTAL por ID + sync_id
 *  - Conflitos: sync_origin + sync_id previnem duplicatas
 * 
 * @version 1.0.0
 */
class DatabaseReplicator {

    private $remoteDb;
    private $localDb;
    private $logFile;
    private $stats = [
        'pulled' => 0,
        'pushed' => 0,
        'errors' => 0,
        'skipped' => 0,
    ];

    public function __construct(\PDO $remoteDb, \PDO $localDb) {
        $this->remoteDb = $remoteDb;
        $this->localDb = $localDb;

        $logDir = defined('SYNC_LOG_DIR') ? SYNC_LOG_DIR : __DIR__ . '/../../../storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $this->logFile = $logDir . '/sync_daemon_' . date('Y-m-d') . '.log';
    }

    // =========================================================================
    // SYNC PRINCIPAL
    // =========================================================================

    /**
     * Executa um ciclo completo de sincronização para dados de REFERÊNCIA.
     * Direção: Remote → Local (full replace)
     */
    public function syncReferenceData(): array {
        $this->stats = ['pulled' => 0, 'pushed' => 0, 'errors' => 0, 'skipped' => 0];
        $tables = defined('SYNC_TABLES') ? SYNC_TABLES : [];

        foreach ($tables as $table => $config) {
            if ($config['priority'] !== 'reference') continue;
            if ($config['direction'] === 'local_to_remote') continue;

            try {
                $this->fullSyncTable($table, 'remote_to_local');
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->log('ERROR', "Falha ao sincronizar tabela $table: " . $e->getMessage());
            }
        }

        return $this->stats;
    }

    /**
     * Executa um ciclo de sincronização para dados TRANSACIONAIS.
     * Direção: Bidirecional (incremental por ID)
     */
    public function syncTransactionalData(): array {
        $this->stats = ['pulled' => 0, 'pushed' => 0, 'errors' => 0, 'skipped' => 0];
        $tables = defined('SYNC_TABLES') ? SYNC_TABLES : [];

        foreach ($tables as $table => $config) {
            if ($config['priority'] !== 'transaction') continue;

            try {
                if ($config['direction'] === 'bidirectional') {
                    // 1. Push: Local → Remote (operações feitas offline)
                    $this->pushLocalToRemote($table);
                    // 2. Pull: Remote → Local (operações novas da Hostinger)
                    $this->pullRemoteToLocal($table);
                } elseif ($config['direction'] === 'remote_to_local') {
                    $this->pullRemoteToLocal($table);
                } elseif ($config['direction'] === 'local_to_remote') {
                    $this->pushLocalToRemote($table);
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->log('ERROR', "Falha sync transacional $table: " . $e->getMessage());
            }
        }

        return $this->stats;
    }

    // =========================================================================
    // FULL SYNC — Dados de referência (produtos, clientes, etc.)
    // =========================================================================

    /**
     * Sincroniza uma tabela inteira substituindo todos os dados locais.
     * Usado para tabelas de referência que mudam pouco e são "read-only" localmente.
     */
    private function fullSyncTable(string $table, string $direction): void {
        $source = ($direction === 'remote_to_local') ? $this->remoteDb : $this->localDb;
        $dest = ($direction === 'remote_to_local') ? $this->localDb : $this->remoteDb;

        // Verificar se tabela existe no destino
        if (!$this->tableExists($dest, $table)) {
            $this->log('WARN', "Tabela '$table' não existe no destino — pulando");
            $this->stats['skipped']++;
            return;
        }

        if (!$this->tableExists($source, $table)) {
            $this->log('WARN', "Tabela '$table' não existe na origem — pulando");
            $this->stats['skipped']++;
            return;
        }

        // Contar registros na origem
        $countStmt = $source->query("SELECT COUNT(*) as total FROM `$table`");
        $total = $countStmt->fetchColumn();

        if ($total == 0) {
            $this->log('INFO', "Tabela '$table' vazia na origem — pulando");
            return;
        }

        // Buscar todos os dados da origem
        $dataStmt = $source->query("SELECT * FROM `$table`");
        $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) return;

        // Obter colunas
        $columns = array_keys($rows[0]);
        $colList = implode(', ', array_map(fn($c) => "`$c`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        // Truncar destino e inserir em batch
        $dest->beginTransaction();
        try {
            // Desabilitar FK checks temporariamente
            $dest->exec("SET FOREIGN_KEY_CHECKS = 0");
            $dest->exec("TRUNCATE TABLE `$table`");

            $insertSql = "INSERT INTO `$table` ($colList) VALUES ($placeholders)";
            $insertStmt = $dest->prepare($insertSql);

            $batchCount = 0;
            foreach ($rows as $row) {
                $insertStmt->execute(array_values($row));
                $batchCount++;
            }

            $dest->exec("SET FOREIGN_KEY_CHECKS = 1");
            $dest->commit();

            $this->stats['pulled'] += $batchCount;
            $this->updateSyncState($table, $batchCount);
            $this->log('OK', "Full sync '$table': $batchCount registros sincronizados");

        } catch (\Exception $e) {
            $dest->rollBack();
            $dest->exec("SET FOREIGN_KEY_CHECKS = 1");
            throw $e;
        }
    }

    // =========================================================================
    // INCREMENTAL SYNC — Dados transacionais (vendas, pré-vendas, etc.)
    // =========================================================================

    /**
     * PULL: Traz novos registros do REMOTO para o LOCAL.
     * Usa o último ID sincronizado para buscar apenas registros novos.
     */
    private function pullRemoteToLocal(string $table): void {
        if (!$this->tableExists($this->remoteDb, $table) || !$this->tableExists($this->localDb, $table)) {
            $this->stats['skipped']++;
            return;
        }

        $lastSyncedId = $this->getLastSyncedId($table, 'pull');

        // Buscar registros novos no remoto (ID > último sincronizado)
        $stmt = $this->remoteDb->prepare("SELECT * FROM `$table` WHERE id > ? ORDER BY id ASC LIMIT 500");
        $stmt->execute([$lastSyncedId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) return;

        $columns = array_keys($rows[0]);

        // Verificar se colunas de sync existem
        $hasSyncOrigin = in_array('sync_origin', $columns);
        $hasSyncId = in_array('sync_id', $columns);

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            // Verificar se já existe no local (por sync_id ou ID)
            if ($hasSyncId && !empty($row['sync_id'])) {
                $existCheck = $this->localDb->prepare("SELECT id FROM `$table` WHERE sync_id = ?");
                $existCheck->execute([$row['sync_id']]);
                if ($existCheck->fetch()) {
                    $skipped++;
                    continue;
                }
            }

            // Verificar por ID direto
            $existCheck = $this->localDb->prepare("SELECT id FROM `$table` WHERE id = ?");
            $existCheck->execute([$row['id']]);
            if ($existCheck->fetch()) {
                $skipped++;
                continue;
            }

            // Inserir no local
            try {
                // Marcar como vindo do remoto
                if ($hasSyncOrigin) $row['sync_origin'] = 'remote';
                if ($hasSyncId && empty($row['sync_id'])) {
                    $row['sync_id'] = 'R-' . $table . '-' . $row['id'];
                }

                $colList = implode(', ', array_map(fn($c) => "`$c`", array_keys($row)));
                $placeholders = implode(', ', array_fill(0, count($row), '?'));

                $this->localDb->prepare("INSERT INTO `$table` ($colList) VALUES ($placeholders)")
                    ->execute(array_values($row));

                $inserted++;
            } catch (\Exception $e) {
                // Registro pode ter FK não satisfeita — log e pular
                $this->log('WARN', "Falha ao inserir $table #" . $row['id'] . " no local: " . $e->getMessage());
                $this->stats['errors']++;
            }
        }

        if ($inserted > 0 || $skipped > 0) {
            $maxId = max(array_column($rows, 'id'));
            $this->updateSyncState($table, $inserted, $maxId, 'pull');
            $this->stats['pulled'] += $inserted;
            $this->stats['skipped'] += $skipped;
            $this->log('OK', "Pull '$table': $inserted novos, $skipped duplicados (último ID: $maxId)");
        }
    }

    /**
     * PUSH: Envia registros do LOCAL (criados offline) para o REMOTO.
     * Só envia registros com sync_origin = 'local'.
     */
    private function pushLocalToRemote(string $table): void {
        if (!$this->tableExists($this->remoteDb, $table) || !$this->tableExists($this->localDb, $table)) {
            $this->stats['skipped']++;
            return;
        }

        // Verificar se a tabela tem coluna sync_origin
        if (!$this->columnExistsIn($this->localDb, $table, 'sync_origin')) {
            return; // Tabela não tem tracking de sync
        }

        // Buscar registros criados localmente que ainda não foram pushados
        $stmt = $this->localDb->prepare("SELECT * FROM `$table` WHERE sync_origin = 'local' ORDER BY id ASC LIMIT 100");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) return;

        $pushed = 0;

        foreach ($rows as $row) {
            $localId = $row['id'];
            $syncId = $row['sync_id'] ?? ('L-' . $table . '-' . $localId);

            // Verificar se já existe no remoto (por sync_id)
            $existCheck = $this->remoteDb->prepare("SELECT id FROM `$table` WHERE sync_id = ?");
            $existCheck->execute([$syncId]);
            $existing = $existCheck->fetch();

            if ($existing) {
                // Já existe no remoto — marcar como sincronizado no local
                $this->localDb->prepare("UPDATE `$table` SET sync_origin = 'synced' WHERE id = ?")
                    ->execute([$localId]);
                continue;
            }

            // Inserir no remoto (sem o ID local — deixa o auto_increment do remoto gerar)
            try {
                $insertRow = $row;
                unset($insertRow['id']); // Remover ID local para gerar novo no remoto
                $insertRow['sync_origin'] = 'local';
                $insertRow['sync_id'] = $syncId;

                $colList = implode(', ', array_map(fn($c) => "`$c`", array_keys($insertRow)));
                $placeholders = implode(', ', array_fill(0, count($insertRow), '?'));

                $this->remoteDb->prepare("INSERT INTO `$table` ($colList) VALUES ($placeholders)")
                    ->execute(array_values($insertRow));

                $remoteId = $this->remoteDb->lastInsertId();

                // Marcar como sincronizado no local
                $this->localDb->prepare("UPDATE `$table` SET sync_origin = 'synced' WHERE id = ?")
                    ->execute([$localId]);

                $pushed++;

                $this->log('OK', "Push '$table': local #$localId → remote #$remoteId (sync_id: $syncId)");

            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->log('ERROR', "Falha push $table local #$localId: " . $e->getMessage());

                // Registrar na fila de pendentes
                $this->registerPendingSync($table, $localId, 'INSERT', $row, $e->getMessage());
            }
        }

        if ($pushed > 0) {
            $this->stats['pushed'] += $pushed;
            $this->log('OK', "Push '$table': $pushed registros enviados ao remoto");
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function tableExists(\PDO $db, string $table): bool {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function columnExistsIn(\PDO $db, string $table, string $column): bool {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getLastSyncedId(string $table, string $direction = 'pull'): int {
        try {
            $stmt = $this->localDb->prepare("SELECT last_synced_id FROM sync_state WHERE table_name = ?");
            $stmt->execute([$table . '_' . $direction]);
            $result = $stmt->fetchColumn();
            return $result ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function updateSyncState(string $table, int $recordsSynced, ?int $lastId = null, string $direction = 'pull'): void {
        try {
            $key = $table . '_' . $direction;
            $stmt = $this->localDb->prepare("
                INSERT INTO sync_state (table_name, last_synced_id, last_synced_at, records_synced, direction)
                VALUES (?, ?, NOW(), ?, ?)
                ON DUPLICATE KEY UPDATE
                    last_synced_id = COALESCE(?, last_synced_id),
                    last_synced_at = NOW(),
                    records_synced = records_synced + ?
            ");
            $stmt->execute([
                $key, $lastId ?? 0, $recordsSynced, $direction,
                $lastId, $recordsSynced
            ]);
        } catch (\Exception $e) {
            $this->log('WARN', "Falha ao atualizar sync_state para $table: " . $e->getMessage());
        }
    }

    private function registerPendingSync(string $table, int $recordId, string $operation, array $data, string $error): void {
        try {
            $this->localDb->prepare("
                INSERT INTO pending_sync (table_name, record_id, operation, record_data, status, error_message)
                VALUES (?, ?, ?, ?, 'error', ?)
            ")->execute([
                $table, $recordId, $operation, json_encode($data, JSON_UNESCAPED_UNICODE), $error
            ]);
        } catch (\Exception $e) {
            $this->log('ERROR', "Falha ao registrar pending_sync: " . $e->getMessage());
        }
    }

    /**
     * Verifica conectividade com o banco remoto.
     */
    public function isRemoteAvailable(): bool {
        try {
            $this->remoteDb->query("SELECT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Retorna estatísticas do último ciclo de sync.
     */
    public function getStats(): array {
        return $this->stats;
    }

    private function log(string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] [$level] $message\n";
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);

        // Imprimir no console se rodando via CLI
        if (php_sapi_name() === 'cli') {
            echo $line;
        }
    }
}
