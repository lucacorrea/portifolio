<?php

declare(strict_types=1);

namespace App\Core;

require_once __DIR__ . '/MigrationThirteenPostcondition.php';

use PDO;
use Throwable;

final class MigrationRunner
{
    use MigrationThirteenPostcondition;

    private const HISTORY_TABLE = 'schema_migrations';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function run(string $directory, int $lockWaitSeconds = 20): bool
    {
        $files = $this->migrationFiles($directory);
        if ($files === []) {
            return true;
        }
        if ($this->historyIsCurrent($files)) {
            return true;
        }

        $lockName = $this->lockName();
        $lockAcquired = false;
        $currentMigration = 'bootstrap';
        try {
            $lockAcquired = $this->acquireLock($lockName, $lockWaitSeconds);
            if (!$lockAcquired) {
                return false;
            }

            if ($this->historyIsCurrent($files)) {
                return true;
            }

            $this->assertCompatibleServer();
            $this->connection->exec('SET SESSION lock_wait_timeout = 30');
            $this->connection->exec('SET SESSION innodb_lock_wait_timeout = 30');
            $historyExisted = $this->tableExists(self::HISTORY_TABLE);
            $this->createHistoryTable();
            if (!$historyExisted && $this->tableExists('perfis')) {
                $this->baselineLegacyDatabase($files);
            }

            $applied = $this->appliedMigrations();
            $knownFiles = array_column($files, null, 'name');
            foreach ($applied as $name => $row) {
                if (!isset($knownFiles[$name])) {
                    throw new MigrationException('O histórico contém uma migration que não existe no código atual.');
                }
                if (!hash_equals($row['checksum'], $knownFiles[$name]['checksum'])) {
                    throw new MigrationException('Uma migration já aplicada foi alterada.');
                }
            }

            foreach ($files as $file) {
                if (isset($applied[$file['name']])) {
                    continue;
                }
                $currentMigration = $file['name'];
                $startedAt = hrtime(true);
                if (!self::supportsVersion($file['version'])) {
                    throw new MigrationException('A migration ainda não foi homologada para execução automática.');
                }
                $this->preflight($file['version']);
                $this->executeSql($file['sql']);
                $postcondition = $this->knownPostcondition($file['version']);
                if ($postcondition !== true) {
                    throw new MigrationException('A migration não atingiu o estado esperado.');
                }
                $elapsedMilliseconds = (int) ((hrtime(true) - $startedAt) / 1_000_000);
                $this->recordMigration($file, 'applied', $elapsedMilliseconds);
            }
            return true;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            error_log('Automatic migration failed at ' . $currentMigration . ': ' . $exception->getMessage());
            if ($exception instanceof MigrationException) {
                throw $exception;
            }
            throw new MigrationException('Não foi possível atualizar automaticamente o banco de dados.', 0, $exception);
        } finally {
            if ($lockAcquired) {
                $this->releaseLock($lockName);
            }
        }
    }

    /** @return array<int, array{version:int,name:string,path:string,sql:string,checksum:string}> */
    private function migrationFiles(string $directory): array
    {
        $realDirectory = realpath($directory);
        if ($realDirectory === false || !is_dir($realDirectory)) {
            throw new MigrationException('Diretório de migrations não encontrado.');
        }

        $paths = glob($realDirectory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        $files = [];
        $expectedVersion = 1;
        foreach ($paths as $path) {
            $name = basename($path);
            if (preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', $name, $matches) !== 1) {
                throw new MigrationException('Nome de migration inválido: ' . $name);
            }
            $version = (int) $matches[1];
            if ($version !== $expectedVersion) {
                throw new MigrationException('A sequência de migrations possui lacuna ou duplicidade.');
            }
            $sql = file_get_contents($path);
            if (!is_string($sql) || trim($sql) === '') {
                throw new MigrationException('Migration vazia ou ilegível: ' . $name);
            }
            $normalizedSql = str_replace(["\r\n", "\r"], "\n", $sql);
            $files[] = [
                'version' => $version,
                'name' => $name,
                'path' => $path,
                'sql' => $sql,
                'checksum' => hash('sha256', $normalizedSql),
            ];
            ++$expectedVersion;
        }
        return $files;
    }

    private function assertCompatibleServer(): void
    {
        $version = (string) $this->connection->query('SELECT VERSION()')->fetchColumn();
        if (stripos($version, 'mariadb') === false) {
            throw new MigrationException('As migrations automáticas exigem MariaDB.');
        }
        if (preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches) !== 1
            || version_compare($matches[1], '10.4.0', '<')
        ) {
            throw new MigrationException('A versão do MariaDB não é compatível com as migrations.');
        }
    }

    private function lockName(): string
    {
        $database = (string) $this->connection->query('SELECT DATABASE()')->fetchColumn();
        return 'yk_migrate_' . substr(hash('sha256', $database), 0, 40);
    }

    public static function supportsVersion(int $version): bool
    {
        return in_array($version, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13], true);
    }

    private function acquireLock(string $name, int $waitSeconds): bool
    {
        $statement = $this->connection->prepare('SELECT GET_LOCK(:lock_name, :wait_seconds)');
        $statement->bindValue('lock_name', $name);
        $statement->bindValue('wait_seconds', max(0, $waitSeconds), PDO::PARAM_INT);
        $statement->execute();
        return (int) $statement->fetchColumn() === 1;
    }

    private function releaseLock(string $name): void
    {
        try {
            $statement = $this->connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->execute(['lock_name' => $name]);
        } catch (Throwable $exception) {
            error_log('Could not release database migration lock: ' . $exception->getMessage());
        }
    }

    private function createHistoryTable(): void
    {
        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(190) NOT NULL PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                mode ENUM('baseline', 'applied') NOT NULL,
                execution_ms INT UNSIGNED NOT NULL DEFAULT 0,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @param array<int, array{version:int,name:string,path:string,sql:string,checksum:string}> $files */
    private function baselineLegacyDatabase(array $files): void
    {
        foreach ($files as $file) {
            if ($this->knownPostcondition($file['version']) !== true) {
                break;
            }
            $this->recordMigration($file, 'baseline', 0);
        }
    }

    /** @return array<string, array{checksum:string,mode:string}> */
    private function appliedMigrations(): array
    {
        $rows = $this->connection->query(
            'SELECT migration, checksum, mode FROM schema_migrations ORDER BY migration'
        )->fetchAll();
        $applied = [];
        foreach ($rows as $row) {
            $applied[(string) $row['migration']] = [
                'checksum' => (string) $row['checksum'],
                'mode' => (string) $row['mode'],
            ];
        }
        return $applied;
    }

    /** @param array<int, array{version:int,name:string,path:string,sql:string,checksum:string}> $files */
    private function historyIsCurrent(array $files): bool
    {
        if (!$this->tableExists(self::HISTORY_TABLE)) {
            return false;
        }
        $applied = $this->appliedMigrations();
        if (count($applied) !== count($files)) {
            return false;
        }
        foreach ($files as $file) {
            if (!isset($applied[$file['name']])
                || !hash_equals($applied[$file['name']]['checksum'], $file['checksum'])
            ) {
                return false;
            }
        }
        return true;
    }

    /** @param array{version:int,name:string,path:string,sql:string,checksum:string} $file */
    private function recordMigration(array $file, string $mode, int $executionMilliseconds): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO schema_migrations (migration, checksum, mode, execution_ms)
             VALUES (:migration, :checksum, :mode, :execution_ms)'
        );
        $statement->execute([
            'migration' => $file['name'],
            'checksum' => $file['checksum'],
            'mode' => $mode,
            'execution_ms' => max(0, $executionMilliseconds),
        ]);
    }

    private function executeSql(string $sql): void
    {
        foreach (SqlStatementSplitter::split($sql) as $statement) {
            if ($this->isCommentOnly($statement)) {
                continue;
            }
            $this->connection->exec($statement);
        }
    }

    private function preflight(int $version): void
    {
        if ($version === 9) {
            $duplicates = $this->scalar(
                "SELECT COUNT(*) FROM (
                    SELECT orcamento_id
                      FROM ordens_servico
                     WHERE orcamento_id IS NOT NULL
                       AND (status <> 'cancelada' OR orcamento_liberado = 0)
                     GROUP BY orcamento_id HAVING COUNT(*) > 1
                ) duplicados"
            );
            if ($duplicates > 0) {
                throw new MigrationException('Existem orçamentos com mais de uma OS operacional.');
            }
        }
        if ($version === 11) {
            $activeFinalizations = $this->scalar(
                'SELECT COUNT(*) FROM (
                    SELECT ordem_servico_id FROM ordem_servico_finalizacoes
                     WHERE ativa = 1 GROUP BY ordem_servico_id HAVING COUNT(*) > 1
                ) duplicados'
            );
            $cashReversals = $this->scalar(
                'SELECT COUNT(*) FROM (
                    SELECT estornado_de_id FROM caixa_movimentacoes
                     WHERE estornado_de_id IS NOT NULL GROUP BY estornado_de_id HAVING COUNT(*) > 1
                ) duplicados'
            );
            $paymentReceipts = $this->scalar(
                'SELECT COUNT(*) FROM (
                    SELECT pagamento_id FROM recibos
                     WHERE pagamento_id IS NOT NULL GROUP BY pagamento_id HAVING COUNT(*) > 1
                ) duplicados'
            );
            if ($activeFinalizations + $cashReversals + $paymentReceipts > 0) {
                throw new MigrationException('Existem duplicidades que impedem criar os índices operacionais.');
            }
        }
    }

    private function isCommentOnly(string $statement): bool
    {
        $withoutComments = preg_replace('/(?:^|\n)\s*(?:--|#)[^\n]*/', '', $statement) ?? $statement;
        $withoutComments = preg_replace('/\/\*.*?\*\//s', '', $withoutComments) ?? $withoutComments;
        return trim($withoutComments) === '';
    }

    private function knownPostcondition(int $version): ?bool
    {
        return match ($version) {
            1 => $this->allTables(['perfis', 'usuarios', 'permissoes', 'perfil_permissoes'])
                && $this->allIndexes([['perfis', 'uk_perfis_nome'], ['usuarios', 'uk_usuarios_usuario']])
                && $this->allForeignKeys(['fk_usuarios_perfil', 'fk_perfil_permissoes_perfil', 'fk_perfil_permissoes_permissao']),
            2 => $this->scalar(
                "SELECT COUNT(*) FROM permissoes
                 WHERE modulo = 'transportadora'
                    OR codigo IN ('funcionario.desativar', 'funcionario.visualizar_produtividade', 'funcionario.visualizar_comissao')"
            ) === 0,
            3 => $this->tableExists('funcionarios'),
            4 => $this->allTables(['produtos', 'servicos']),
            5 => $this->allTables(['clientes', 'orcamentos', 'orcamento_itens']),
            6 => $this->tableExists('ordens_servico') && !$this->columnExists('orcamentos', 'responsavel_id'),
            7 => $this->allTables(['ordem_servico_itens', 'agenda_lembretes'])
                && $this->allColumns('ordens_servico', ['equipamento_tipo', 'diagnostico', 'subtotal_servicos', 'total', 'cancelada_em']),
            8 => $this->allTables([
                'ordem_servico_funcionarios', 'ordem_servico_cancelamentos', 'ordem_servico_finalizacoes',
                'ordem_servico_execucao_itens', 'estoque_autorizacoes', 'estoque_movimentacoes',
                'caixa_movimentacoes', 'ordem_servico_pagamentos', 'contas_receber',
                'contas_receber_eventos', 'configuracoes_empresa',
            ]) && $this->allColumns('ordens_servico', ['orcamento_liberado', 'ordem_substituta_id', 'valor_aprovado_orcamento']),
            9 => $this->allTables(['configuracoes_fiscais', 'documentos_fiscais', 'recibos', 'boletos'])
                && $this->allColumns('funcionarios', ['foto', 'cpf_numero', 'cnh_numero_registro'])
                && $this->allColumns('ordens_servico', ['orcamento_operacional_chave']),
            10 => $this->allTables(['vendas_avulsas', 'venda_avulsa_itens'])
                && $this->allColumns('estoque_autorizacoes', ['utilizada_em', 'movimentacao_id'])
                && $this->allForeignKeys(['fk_estoque_aut_movimentacao'])
                && $this->permissionSatisfied('venda_avulsa.visualizar')
                && $this->permissionSatisfied('venda_avulsa.criar')
                && $this->permissionSatisfied('venda_avulsa.estornar'),
            11 => $this->migrationElevenSatisfied(),
            12 => $this->permissionSatisfied('cliente.importar'),
            13 => $this->migrationThirteenSatisfied(),
            default => null,
        };
    }

    private function migrationElevenSatisfied(): bool
    {
        if (!$this->allColumns('ordens_servico', ['excluida_em', 'excluida_por', 'motivo_exclusao'])
            || !$this->allColumns('ordem_servico_finalizacoes', ['status_origem', 'estornado_por', 'estornado_em', 'motivo_estorno', 'finalizacao_ativa_chave'])
            || !$this->allColumns('recibos', ['cliente_nome', 'cliente_documento', 'os_numero', 'pagamento_recebido_em', 'empresa_nome', 'empresa_logo'])
            || !$this->allForeignKeys([
                'fk_os_exclusao_usuario', 'fk_os_finalizacoes_estorno_usuario', 'fk_os_execucao_finalizacao',
                'fk_estoque_estornado_de', 'fk_recibos_pagamento',
            ])
            || !$this->allIndexes([
                ['ordem_servico_finalizacoes', 'uq_os_finalizacao_ativa'],
                ['estoque_movimentacoes', 'uq_estoque_estornado_de'],
                ['caixa_movimentacoes', 'uq_caixa_estornado_de'],
                ['recibos', 'uq_recibos_pagamento'],
            ])
        ) {
            return false;
        }
        return $this->permissionSatisfied('os.estornar') && $this->scalar(
            'SELECT COUNT(*)
               FROM ordem_servico_execucao_itens item
               JOIN ordem_servico_finalizacoes finalizacao
                 ON finalizacao.ordem_servico_id = item.ordem_servico_id AND finalizacao.ativa = 1
              WHERE item.finalizacao_id IS NULL'
        ) === 0;
    }

    private function permissionSatisfied(string $code): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM permissoes WHERE codigo = :code');
        $statement->execute(['code' => $code]);
        if ((int) $statement->fetchColumn() !== 1) {
            return false;
        }
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
               FROM perfis perfil
              WHERE perfil.nome = 'Administrador'
                AND NOT EXISTS (
                    SELECT 1 FROM perfil_permissoes pp
                    JOIN permissoes permissao ON permissao.id = pp.permissao_id
                    WHERE pp.perfil_id = perfil.id AND permissao.codigo = :code
                )"
        );
        $statement->execute(['code' => $code]);
        return (int) $statement->fetchColumn() === 0;
    }

    /** @param string[] $tables */
    private function allTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                return false;
            }
        }
        return true;
    }

    private function tableExists(string $table): bool
    {
        return $this->informationSchemaCount('TABLES', 'TABLE_NAME', $table) === 1;
    }

    /** @param string[] $columns */
    private function allColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!$this->columnExists($table, $column)) {
                return false;
            }
        }
        return true;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $statement->execute(['table_name' => $table, 'column_name' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    /** @param array<int, array{0:string,1:string}> $indexes */
    private function allIndexes(array $indexes): bool
    {
        foreach ($indexes as [$table, $index]) {
            $statement = $this->connection->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
            );
            $statement->execute(['table_name' => $table, 'index_name' => $index]);
            if ((int) $statement->fetchColumn() < 1) {
                return false;
            }
        }
        return true;
    }

    /** @param string[] $constraints */
    private function allForeignKeys(array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if ($this->informationSchemaCount('REFERENTIAL_CONSTRAINTS', 'CONSTRAINT_NAME', $constraint) !== 1) {
                return false;
            }
        }
        return true;
    }

    private function informationSchemaCount(string $table, string $column, string $value): int
    {
        $allowed = ['TABLES' => 'TABLE_NAME', 'REFERENTIAL_CONSTRAINTS' => 'CONSTRAINT_NAME'];
        if (($allowed[$table] ?? null) !== $column) {
            throw new MigrationException('Consulta interna de schema inválida.');
        }
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.' . $table
            . ' WHERE ' . ($table === 'TABLES' ? 'TABLE_SCHEMA' : 'CONSTRAINT_SCHEMA')
            . ' = DATABASE() AND ' . $column . ' = :value'
        );
        $statement->execute(['value' => $value]);
        return (int) $statement->fetchColumn();
    }

    private function scalar(string $sql): int
    {
        return (int) $this->connection->query($sql)->fetchColumn();
    }
}
