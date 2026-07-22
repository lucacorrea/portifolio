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

function db_index_exists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function db_foreign_key_exists(PDO $pdo, string $table, string $constraint): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = ?
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $stmt->execute([$table, $constraint]);
    return (int)$stmt->fetchColumn() > 0;
}

function db_secretarias_relatorio_seed(): array {
    return [
        ['COMIÇÃO DE CONTRATAÇÃO DE COARI-CCC', 'CCC2026', '#D9E3F4'],
        ['CONTROLADORIA GERAL DO MUNICICÍPIO - CGM', 'CGM2026', '#E2F0D9'],
        ['COORDENADORIA REGIONAL DE EDUCAÇÃO DE COARI', 'CREC2026', '#FCE4D6'],
        ['PROCURADORIA GERAL DO MUNICÍPIO - PGM', 'PGM2026', '#FFF2CC'],
        ['SECRETARIA DE ESTADO DA EDUCAÇÃO E DESPORTO ESCOLAR COORDENADORIA REGIONAL DE EDUCAÇÃO DE COARI-SEDUC', 'SEDUC2026', '#EADCF8'],
        ['SECRETARIA MUNICIPAL DA CASA CIVIL - SMCC', 'SMCC2026', '#C9DAF8'],
        ['SECRETARIA MUNICIPAL DE ADMINISTRAÇÃO - SEMAD', 'SEMAD2026', '#D9EAD3'],
        ['SECRETARIA MUNICIPAL DE ASSISTÊNCIA SOCIAL - SEMAS', 'SEMAS2026', '#F4CCCC'],
        ['SECRETARIA MUNICIPAL DE CIÊNCIA, TECNOLOGIA E INOVAÇÃO', 'SMCTI2026', '#D0E0E3'],
        ['SECRETARIA MUNICIPAL DE COMUNICAÇÃO - SEMCOM', 'SEMCOM2026', '#FCE5CD'],
        ['SECRETARIA MUNICIPAL DE CULTURA E TURISMO - SECULT', 'SECULT2026', '#D5A6BD'],
        ['SECRETARIA MUNICIPAL DE DESENVOLVIMENTO RURAL E ECONÔMICO - SMDRE', 'SMDRE2026', '#B6D7A8'],
        ['SECRETARIA MUNICIPAL DE EDUCAÇÃO - SEMED', 'SEMED2026', '#A4C2F4'],
        ['SECRETARIA MUNICIPAL DE ESPORTE - SEMESP', 'SEMESP2026', '#B4A7D6'],
        ['SECRETARIA MUNICIPAL DE FAZENDA - SEMFAZ', 'SEMFAZ2026', '#FFD966'],
        ['SECRETARIA MUNICIPAL DE INDÚSTRIA E COMERCIO - SEMIC', 'SEC2026', '#CFE2F3'],
        ['SECRETARIA MUNICIPAL DE LIMPEZA PÚBLICA - SEMLIP', 'SEMLIP2026', '#D9D2E9'],
        ['SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMMA', 'SEMMA2026', '#B7E1CD'],
        ['SECRETARIA MUNICIPAL DE OBRAS - SEMOB', 'SEMOB2026', '#F9CB9C'],
        ['SECRETARIA MUNICIPAL DE PLANEJAMENTO - SEMPLAN', 'SEMPLAN2026', '#CFE2F3'],
        ['SECRETARIA MUNICIPAL DE SAÚDE - SEMSA', 'SEMSA2026', '#EA9999'],
        ['SECRETARIA MUNICIPAL DE SEGURANÇA PÚBLICA E DEFESA SOCIAL - AEROPORTO', 'AEROPORTO2026', '#B7B7B7'],
        ['SECRETARIA MUNICIPAL DE SEGURANÇA PÚBLICA E DEFESA SOCIAL - SMSPDS', 'SMSPDS2026', '#A2C4C9'],
        ['SECRETARIA MUNICIPAL DE TERRAS E HABITAÇÃO - SEMTH', 'SEMTH2026', '#D5E8D4'],
        ['SECRETARIA MUNICIPAL EXTRAORDINÁRIA', 'SME2026', '#E6B8AF'],
        ['SECRETÁRIO MUNICIPAL DE RELAÇÕES INSTITUCIONAIS', 'SMRI2026', '#D9D9D9'],
    ];
}

function db_sync_secretarias_relatorio(PDO $pdo): void {
    if (!db_table_exists($pdo, 'secretarias')) {
        return;
    }

    db_add_column_if_missing($pdo, 'secretarias', 'cor_relatorio', "cor_relatorio VARCHAR(7) DEFAULT '#D9E3F4' AFTER codigo_acesso");

    $find = $pdo->prepare("SELECT id FROM secretarias WHERE codigo_acesso = ? OR nome = ? LIMIT 1");
    $findAlias = $pdo->prepare("SELECT id FROM secretarias WHERE nome = ? LIMIT 1");
    $update = $pdo->prepare("UPDATE secretarias SET nome = ?, codigo_acesso = ?, cor_relatorio = ? WHERE id = ?");
    $insert = $pdo->prepare("INSERT INTO secretarias (nome, codigo_acesso, cor_relatorio, responsavel) VALUES (?, ?, ?, NULL)");
    $aliases = [
        'SEMSA2026' => ['Saúde'],
        'SEMED2026' => ['Educação'],
        'SEMOB2026' => ['Obras'],
        'SEMAS2026' => ['Assistência Social'],
    ];

    foreach (db_secretarias_relatorio_seed() as $secretaria) {
        [$nome, $codigo, $cor] = $secretaria;
        $find->execute([$codigo, $nome]);
        $id = $find->fetchColumn();

        if (!$id && isset($aliases[$codigo])) {
            foreach ($aliases[$codigo] as $alias) {
                $findAlias->execute([$alias]);
                $id = $findAlias->fetchColumn();
                if ($id) {
                    break;
                }
            }
        }

        if ($id) {
            $update->execute([$nome, $codigo, $cor, (int)$id]);
        } else {
            $insert->execute([$nome, $codigo, $cor]);
        }
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

        db_sync_secretarias_relatorio($pdo);

        if (db_table_exists($pdo, 'oficios')) {
            db_add_column_if_missing($pdo, 'oficios', 'fornecedor_indicado_id', "fornecedor_indicado_id INT NULL AFTER usuario_id");
            if (!db_index_exists($pdo, 'oficios', 'idx_oficios_fornecedor_indicado')) {
                $pdo->exec("CREATE INDEX idx_oficios_fornecedor_indicado ON oficios (fornecedor_indicado_id)");
            }
            if (db_table_exists($pdo, 'fornecedores') && !db_foreign_key_exists($pdo, 'oficios', 'fk_oficios_fornecedor_indicado')) {
                $pdo->exec("
                    ALTER TABLE oficios
                    ADD CONSTRAINT fk_oficios_fornecedor_indicado
                    FOREIGN KEY (fornecedor_indicado_id) REFERENCES fornecedores(id)
                    ON DELETE SET NULL
                ");
            }
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
