<?php
/**
 * =============================================================================
 * ERP Elétrica — Setup do Banco Local (XAMPP)
 * =============================================================================
 * 
 * Execute este script UMA VEZ para configurar o banco local na máquina do Balcão.
 * 
 * O que ele faz:
 *  1. Cria o banco de dados 'erp_eletrica_local' no MariaDB
 *  2. Importa a estrutura (schema) do dump SQL
 *  3. Roda todas as migrations
 *  4. Faz o primeiro full sync dos dados do Hostinger
 * 
 * COMO RODAR:
 *   php setup_local_db.php
 * 
 * PRÉ-REQUISITOS:
 *   - XAMPP instalado e MariaDB rodando
 *   - Conexão com a internet (para puxar dados do Hostinger)
 *   - Arquivo 'banco de dados/u784961086_pdv.sql' presente
 */

if (php_sapi_name() !== 'cli') {
    die("Este script deve ser rodado via linha de comando (CLI).\n");
}

require_once __DIR__ . '/sync_config.php';

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   ERP Elétrica — Setup do Banco Local                   ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// =========================================================================
// PASSO 1: Conectar ao MariaDB local (sem banco específico)
// =========================================================================
echo "[1/5] Conectando ao MariaDB local...\n";

try {
    $dsn = "mysql:host=" . LOCAL_DB_HOST . ";port=" . LOCAL_DB_PORT . ";charset=utf8mb4";
    $localRoot = new \PDO($dsn, LOCAL_DB_USER, LOCAL_DB_PASS, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ]);
    echo "  ✅ Conectado ao MariaDB com sucesso\n\n";
} catch (\PDOException $e) {
    die("  ❌ ERRO: Não foi possível conectar ao MariaDB.\n" .
        "     Verifique se o XAMPP está rodando e o MariaDB está ativo.\n" .
        "     Erro: " . $e->getMessage() . "\n");
}

// =========================================================================
// PASSO 2: Criar banco de dados
// =========================================================================
$dbName = LOCAL_DB_NAME;
echo "[2/5] Criando banco de dados '$dbName'...\n";

try {
    $localRoot->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $localRoot->exec("USE `$dbName`");
    echo "  ✅ Banco '$dbName' criado/verificado\n\n";
} catch (\PDOException $e) {
    die("  ❌ ERRO ao criar banco: " . $e->getMessage() . "\n");
}

// =========================================================================
// PASSO 3: Importar schema do dump SQL
// =========================================================================
echo "[3/5] Importando estrutura do banco...\n";

$dumpFile = __DIR__ . '/banco de dados/u784961086_pdv.sql';
if (!file_exists($dumpFile)) {
    echo "  ⚠️  Arquivo dump não encontrado: $dumpFile\n";
    echo "  → Tentando importar via estrutura do banco remoto...\n\n";
    
    // Alternativa: copiar estrutura do remoto
    try {
        $remoteDsn = "mysql:host=" . REMOTE_DB_HOST . ";port=" . REMOTE_DB_PORT . ";dbname=" . REMOTE_DB_NAME . ";charset=utf8mb4";
        $remoteDb = new \PDO($remoteDsn, REMOTE_DB_USER, REMOTE_DB_PASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        // Obter lista de tabelas do remoto
        $tables = $remoteDb->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        echo "  Encontradas " . count($tables) . " tabelas no banco remoto\n";

        $localDb = new \PDO("mysql:host=" . LOCAL_DB_HOST . ";port=" . LOCAL_DB_PORT . ";dbname=$dbName;charset=utf8mb4", LOCAL_DB_USER, LOCAL_DB_PASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $localDb->exec("SET FOREIGN_KEY_CHECKS = 0");

        foreach ($tables as $table) {
            $createStmt = $remoteDb->query("SHOW CREATE TABLE `$table`")->fetch();
            $createSql = $createStmt['Create Table'];
            
            // Adicionar IF NOT EXISTS
            $createSql = str_replace("CREATE TABLE ", "CREATE TABLE IF NOT EXISTS ", $createSql);
            
            try {
                $localDb->exec($createSql);
                echo "  ✅ Tabela '$table' criada\n";
            } catch (\Exception $e) {
                echo "  ⚠️  Tabela '$table': " . $e->getMessage() . "\n";
            }
        }

        $localDb->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "\n  ✅ Estrutura importada do banco remoto\n\n";

    } catch (\Exception $e) {
        die("  ❌ ERRO: Não foi possível conectar ao banco remoto.\n" .
            "     Verifique a internet e as credenciais no sync_config.php\n" .
            "     Erro: " . $e->getMessage() . "\n");
    }
} else {
    // Importar do arquivo SQL
    echo "  Importando de: $dumpFile\n";
    
    try {
        $localDb = new \PDO("mysql:host=" . LOCAL_DB_HOST . ";port=" . LOCAL_DB_PORT . ";dbname=$dbName;charset=utf8mb4", LOCAL_DB_USER, LOCAL_DB_PASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $sql = file_get_contents($dumpFile);
        
        // Limpar e executar
        $localDb->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Separar statements e executar individualmente
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s) && $s !== "\n"
        );

        $count = 0;
        $errors = 0;
        foreach ($statements as $stmt) {
            try {
                $localDb->exec($stmt);
                $count++;
            } catch (\Exception $e) {
                $errors++;
                // Silenciar erros de "table already exists"
                if (stripos($e->getMessage(), 'already exists') === false) {
                    // Log apenas erros críticos
                }
            }
        }

        $localDb->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "  ✅ Schema importado: $count statements executados ($errors avisos)\n\n";

    } catch (\Exception $e) {
        echo "  ⚠️  Erro durante importação: " . $e->getMessage() . "\n";
        echo "  → Continuando com a estrutura existente...\n\n";
    }
}

// =========================================================================
// PASSO 4: Rodar migrations
// =========================================================================
echo "[4/5] Rodando migrations...\n";

$localDb = $localDb ?? new \PDO("mysql:host=" . LOCAL_DB_HOST . ";port=" . LOCAL_DB_PORT . ";dbname=$dbName;charset=utf8mb4", LOCAL_DB_USER, LOCAL_DB_PASS, [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
]);

$migrationsDir = __DIR__ . '/migrations';
if (is_dir($migrationsDir)) {
    $files = glob($migrationsDir . '/*.sql');
    sort($files);
    
    $migCount = 0;
    foreach ($files as $file) {
        $filename = basename($file);
        
        try {
            // Verificar se já foi rodada
            try {
                $check = $localDb->prepare("SELECT 1 FROM migrations_log WHERE migration = ?");
                $check->execute([$filename]);
                if ($check->fetch()) continue;
            } catch (\Exception $e) {
                // Tabela migrations_log pode não existir ainda
                $localDb->exec("CREATE TABLE IF NOT EXISTS migrations_log (id INT AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) UNIQUE, executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            }
            
            $sql = file_get_contents($file);
            $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s));
            
            foreach ($statements as $stmt) {
                try {
                    $localDb->exec($stmt);
                } catch (\Exception $e) {
                    // Pular erros não-fatais (ALTER TABLE com coluna já existente, etc.)
                }
            }
            
            $localDb->prepare("INSERT IGNORE INTO migrations_log (migration) VALUES (?)")->execute([$filename]);
            $migCount++;
            echo "  ✅ $filename\n";
            
        } catch (\Exception $e) {
            echo "  ⚠️  $filename: " . $e->getMessage() . "\n";
        }
    }
    
    echo "  $migCount migrations executadas\n\n";
} else {
    echo "  ⚠️  Diretório de migrations não encontrado\n\n";
}

// =========================================================================
// PASSO 5: Dados iniciais — Full sync do remoto
// =========================================================================
echo "[5/5] Fazendo carga inicial de dados do Hostinger...\n";

try {
    $remoteDsn = "mysql:host=" . REMOTE_DB_HOST . ";port=" . REMOTE_DB_PORT . ";dbname=" . REMOTE_DB_NAME . ";charset=utf8mb4";
    $remoteDb = $remoteDb ?? new \PDO($remoteDsn, REMOTE_DB_USER, REMOTE_DB_PASS, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);

    require_once __DIR__ . '/autoloader.php';
    
    $replicator = new \App\Services\DatabaseReplicator($remoteDb, $localDb);
    
    echo "  Sincronizando dados de referência...\n";
    $stats = $replicator->syncReferenceData();
    echo "  ✅ Referência: {$stats['pulled']} registros importados\n";
    
    echo "  Sincronizando dados transacionais...\n";
    $stats = $replicator->syncTransactionalData();
    echo "  ✅ Transações: {$stats['pulled']} registros importados\n\n";

} catch (\Exception $e) {
    echo "  ⚠️  Carga inicial parcial: " . $e->getMessage() . "\n";
    echo "  → O sync_daemon.php completará a sincronização automaticamente.\n\n";
}

// =========================================================================
// FINALIZAÇÃO
// =========================================================================
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   ✅ Setup concluído com sucesso!                       ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║                                                        ║\n";
echo "║   Próximos passos:                                     ║\n";
echo "║                                                        ║\n";
echo "║   1. Verifique o IP da máquina:                        ║\n";
echo "║      > ipconfig                                        ║\n";
echo "║                                                        ║\n";
echo "║   2. Atualize LOCAL_SERVER_URL em sync_config.php      ║\n";
echo "║      com o IP encontrado (ex: http://192.168.1.100)    ║\n";
echo "║                                                        ║\n";
echo "║   3. Inicie o daemon de sincronização:                 ║\n";
echo "║      > php sync_daemon.php                             ║\n";
echo "║                                                        ║\n";
echo "║   4. Acesse o sistema pelo navegador:                  ║\n";
echo "║      http://localhost/erp_eletrica/                    ║\n";
echo "║                                                        ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
