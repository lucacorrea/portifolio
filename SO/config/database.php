<?php
// config/database.php

$host = 'localhost';
$db_user = 'u784961086_so';
$db_pass = 'Y>g39k3ql'; // Senha padrão do XAMPP é vazia
$dbname = 'u784961086_so';
$schema_file = __DIR__ . '/../database/schema.sql';

function db_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function db_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetchColumn();
}

function db_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
    if (!db_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

try {
    // 1. Conecta ao MySQL (sem o banco ainda)
    $pdo = new PDO("mysql:host=$host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Cria o banco se não existir
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Conecta especificamente ao banco sgao
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 4. Se a tabela 'usuarios' não existir, roda o schema inicial
    $query = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if (!$query->fetch()) {
        $sql = file_get_contents($schema_file);
        $pdo->exec($sql);
    }

    // 5. Auto-migração: adicionar colunas/tabelas se não existirem
    try {
        if (db_table_exists($pdo, 'usuarios')) {
            $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN nivel ENUM('ADMIN', 'SUPORTE', 'SECRETARIO', 'CASA_CIVIL', 'SEFAZ', 'FUNCIONARIO') NOT NULL");
        }

        if (db_table_exists($pdo, 'oficios')) {
            db_add_column_if_missing($pdo, 'oficios', 'arquivo_orcamento', "arquivo_orcamento VARCHAR(255) DEFAULT NULL AFTER usuario_id");
            db_add_column_if_missing($pdo, 'oficios', 'arquivo_oficio', "arquivo_oficio VARCHAR(255) DEFAULT NULL AFTER arquivo_orcamento");
            db_add_column_if_missing($pdo, 'oficios', 'valor_orcamento', "valor_orcamento DECIMAL(15,2) NULL DEFAULT NULL AFTER arquivo_oficio");
            db_add_column_if_missing($pdo, 'oficios', 'resumo_itens', "resumo_itens TEXT NULL AFTER justificativa");
            $pdo->exec("ALTER TABLE oficios MODIFY COLUMN status ENUM('PENDENTE_ITENS', 'ENVIADO', 'EM_ANALISE', 'APROVADO', 'REPROVADO', 'ARQUIVADO') DEFAULT 'PENDENTE_ITENS'");
        }

        $createdAnexosTable = false;
        if (!db_table_exists($pdo, 'oficio_anexos')) {
            $pdo->exec("CREATE TABLE oficio_anexos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                oficio_id INT NOT NULL,
                caminho VARCHAR(255) NOT NULL,
                tipo ENUM('ORCAMENTO', 'OFICIO') NOT NULL,
                nome_original VARCHAR(255),
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (oficio_id) REFERENCES oficios(id) ON DELETE CASCADE
            )");
            $createdAnexosTable = true;
        } else {
            db_add_column_if_missing($pdo, 'oficio_anexos', 'nome_original', "nome_original VARCHAR(255) NULL AFTER tipo");
            db_add_column_if_missing($pdo, 'oficio_anexos', 'criado_em', "criado_em DATETIME DEFAULT CURRENT_TIMESTAMP AFTER nome_original");
        }

        if ($createdAnexosTable && db_column_exists($pdo, 'oficios', 'arquivo_orcamento') && db_column_exists($pdo, 'oficios', 'arquivo_oficio')) {
            $stmt = $pdo->query("SELECT id, arquivo_orcamento, arquivo_oficio FROM oficios WHERE arquivo_orcamento IS NOT NULL OR arquivo_oficio IS NOT NULL");
            $oficios = $stmt->fetchAll();
            foreach ($oficios as $o) {
                if (!empty($o['arquivo_orcamento'])) {
                    $pdo->prepare("INSERT INTO oficio_anexos (oficio_id, caminho, tipo, nome_original) VALUES (?, ?, 'ORCAMENTO', ?)")
                        ->execute([$o['id'], $o['arquivo_orcamento'], basename((string)$o['arquivo_orcamento'])]);
                }
                if (!empty($o['arquivo_oficio'])) {
                    $pdo->prepare("INSERT INTO oficio_anexos (oficio_id, caminho, tipo, nome_original) VALUES (?, ?, 'OFICIO', ?)")
                        ->execute([$o['id'], $o['arquivo_oficio'], basename((string)$o['arquivo_oficio'])]);
                }
            }
        }

        if (db_table_exists($pdo, 'itens_aquisicao') && !db_column_exists($pdo, 'itens_aquisicao', 'oficio_item_id')) {
            $pdo->exec("ALTER TABLE itens_aquisicao ADD COLUMN oficio_item_id INT NULL AFTER aquisicao_id");
            $pdo->exec("CREATE INDEX idx_itens_aquisicao_oficio_item ON itens_aquisicao (oficio_item_id)");
        }

        if (db_table_exists($pdo, 'itens_oficio')) {
            db_add_column_if_missing($pdo, 'itens_oficio', 'valor_unitario', "valor_unitario DECIMAL(15,2) NULL DEFAULT 0.00 AFTER unidade");
        }
    } catch (PDOException $e) {
        throw new PDOException("Erro ao atualizar a estrutura do banco: " . $e->getMessage(), (int)$e->getCode(), $e);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados MySQL: " . $e->getMessage());
}

function getPDO() {
    global $pdo;
    return $pdo;
}
