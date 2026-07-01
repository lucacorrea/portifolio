<?php

require_once __DIR__ . '/assets/conexao.php'; // Defines $pdo

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Connection failed.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    // 1. Create `solicitacoes` table
    echo "Checking 'solicitacoes' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS solicitacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            solicitante_id INT UNSIGNED NOT NULL,
            ajuda_tipo_id INT NULL,
            resumo_caso TEXT,
            data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'Aberto',
            created_by VARCHAR(100),
            origem VARCHAR(20) NULL,
            INDEX (solicitante_id),
            INDEX (data_solicitacao),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'solicitacoes' ensured.\n";

    $solCols = $pdo->query("SHOW COLUMNS FROM solicitacoes LIKE 'origem'")->fetchAll();
    if (count($solCols) === 0) {
        $pdo->exec("ALTER TABLE solicitacoes ADD COLUMN origem VARCHAR(20) NULL AFTER created_by");
        echo "Column 'origem' added to 'solicitacoes'.\n";
    } else {
        echo "Column 'origem' already exists in 'solicitacoes'.\n";
    }

    // 2. Modify `ajudas_entregas`
    echo "Checking columns in 'ajudas_entregas'...\n";
    $cols = $pdo->query("SHOW COLUMNS FROM ajudas_entregas LIKE 'solicitacao_id'")->fetchAll();
    if (count($cols) === 0) {
        $pdo->exec("ALTER TABLE ajudas_entregas ADD COLUMN solicitacao_id INT NULL AFTER pessoa_id");
        echo "Column 'solicitacao_id' added to 'ajudas_entregas'.\n";
    } else {
        echo "Column 'solicitacao_id' already exists.\n";
    }

    $idx = $pdo->query("SHOW INDEX FROM ajudas_entregas WHERE Key_name = 'idx_entregas_solicitacao'")->fetchAll();
    if (count($idx) === 0) {
        $pdo->exec("CREATE INDEX idx_entregas_solicitacao ON ajudas_entregas (solicitacao_id)");
        echo "Index 'idx_entregas_solicitacao' added.\n";
    } else {
        echo "Index 'idx_entregas_solicitacao' already exists.\n";
    }

    // 3. Migrate initial requests from solicitantes without duplicating existing rows
    echo "Migrating initial requests from 'solicitantes' to 'solicitacoes'...\n";

    $candidates = ['responsavel', 'responsavel_cadastro', 'servidor', 'servidor_cadastro', 'usuario_responsavel', 'usuario_cadastro', 'criado_por', 'created_by', 'usuario_nome'];

    $tableCols = $pdo->query("SHOW COLUMNS FROM solicitantes")->fetchAll(PDO::FETCH_COLUMN);
    $tableColsMap = array_fill_keys(array_map('strtolower', $tableCols), true);

    $respExpr = "NULL";
    foreach ($candidates as $c) {
        if (isset($tableColsMap[strtolower($c)])) {
            $respExpr = $c;
            break;
        }
    }

    $hasAjuda = isset($tableColsMap['ajuda_tipo_id']);
    $hasResumo = isset($tableColsMap['resumo_caso']);
    $hasCreated = isset($tableColsMap['created_at']);

    if (!$hasAjuda && !$hasResumo) {
        echo "No initial request columns found in 'solicitantes'. Skipped data migration.\n";
    } else {
        $fields = ["id"];
        $fields[] = $hasAjuda ? "ajuda_tipo_id" : "NULL AS ajuda_tipo_id";
        $fields[] = $hasResumo ? "resumo_caso" : "NULL AS resumo_caso";
        $fields[] = $hasCreated ? "created_at" : "NULL AS created_at";
        $fields[] = "$respExpr AS responsavel";

        $stmt = $pdo->query("SELECT " . implode(', ', $fields) . " FROM solicitantes");
        $existsStmt = $pdo->prepare("
            SELECT id
            FROM solicitacoes
            WHERE solicitante_id = :solicitante_id
              AND COALESCE(ajuda_tipo_id, 0) = COALESCE(:ajuda_tipo_id, 0)
              AND COALESCE(TRIM(resumo_caso), '') = COALESCE(:resumo_caso, '')
              AND DATE(COALESCE(data_solicitacao, '1000-01-01')) = DATE(COALESCE(:data_solicitacao, '1000-01-01'))
            ORDER BY id ASC
            LIMIT 1
        ");
        $cadastroStmt = $pdo->prepare("
            SELECT id
            FROM solicitacoes
            WHERE solicitante_id = :solicitante_id
              AND origem = 'cadastro'
            ORDER BY id ASC
            LIMIT 1
        ");
        $dateStmt = $pdo->prepare("
            SELECT id
            FROM solicitacoes
            WHERE solicitante_id = :solicitante_id
              AND data_solicitacao = :data_solicitacao
            ORDER BY id ASC
            LIMIT 1
        ");
        $markCadastroStmt = $pdo->prepare("
            UPDATE solicitacoes
               SET origem = 'cadastro'
             WHERE id = :id
             LIMIT 1
        ");
        $markDuplicadasStmt = $pdo->prepare("
            UPDATE solicitacoes
               SET origem = 'cadastro_duplicada'
             WHERE solicitante_id = :solicitante_id
               AND id <> :cadastro_id
               AND data_solicitacao = :data_solicitacao
               AND COALESCE(origem, '') <> 'cadastro'
        ");
        $ins = $pdo->prepare("
            INSERT INTO solicitacoes
                (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, created_by, status, origem)
            VALUES
                (:solicitante_id, :ajuda_tipo_id, :resumo_caso, :data_solicitacao, :created_by, 'Aberto', 'cadastro')
        ");

        $migrated = 0;
        $skipped = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sid = (int) $row['id'];
            $aid = ($row['ajuda_tipo_id'] ?? null) !== null ? (int) $row['ajuda_tipo_id'] : null;
            $res = ($row['resumo_caso'] ?? null) !== null && trim((string) $row['resumo_caso']) !== ''
                ? trim((string) $row['resumo_caso'])
                : null;
            $dt = ($row['created_at'] ?? null) ?: date('Y-m-d H:i:s');
            $rsp = ($row['responsavel'] ?? null) ?: null;

            if ($aid === null && $res === null) {
                $skipped++;
                continue;
            }

            $cadastroStmt->execute([':solicitante_id' => $sid]);
            $cadastroId = (int)($cadastroStmt->fetchColumn() ?: 0);
            if ($cadastroId > 0) {
                $markDuplicadasStmt->execute([
                    ':solicitante_id' => $sid,
                    ':cadastro_id' => $cadastroId,
                    ':data_solicitacao' => $dt,
                ]);
                $skipped++;
                continue;
            }

            $dateStmt->execute([
                ':solicitante_id' => $sid,
                ':data_solicitacao' => $dt,
            ]);
            $legacyId = (int)($dateStmt->fetchColumn() ?: 0);
            if ($legacyId > 0) {
                $markCadastroStmt->execute([':id' => $legacyId]);
                $markDuplicadasStmt->execute([
                    ':solicitante_id' => $sid,
                    ':cadastro_id' => $legacyId,
                    ':data_solicitacao' => $dt,
                ]);
                $skipped++;
                continue;
            }

            $existsStmt->execute([
                ':solicitante_id' => $sid,
                ':ajuda_tipo_id' => $aid,
                ':resumo_caso' => $res,
                ':data_solicitacao' => $dt,
            ]);
            $legacyId = (int)($existsStmt->fetchColumn() ?: 0);
            if ($legacyId > 0) {
                $markCadastroStmt->execute([':id' => $legacyId]);
                $markDuplicadasStmt->execute([
                    ':solicitante_id' => $sid,
                    ':cadastro_id' => $legacyId,
                    ':data_solicitacao' => $dt,
                ]);
                $skipped++;
                continue;
            }

            $ins->execute([
                ':solicitante_id' => $sid,
                ':ajuda_tipo_id' => $aid,
                ':resumo_caso' => $res,
                ':data_solicitacao' => $dt,
                ':created_by' => $rsp,
            ]);
            $migrated++;
        }
        echo "Migrated $migrated initial request(s). Skipped $skipped existing/empty record(s).\n";
    }

    $pdo->commit();
    echo "Migration completed successfully.\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
