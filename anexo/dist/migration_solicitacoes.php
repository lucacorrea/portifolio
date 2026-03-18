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
            solicitante_id INT NOT NULL,
            ajuda_tipo_id INT NULL,
            resumo_caso TEXT,
            data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'Aberto',
            created_by VARCHAR(100),
            INDEX (solicitante_id),
            FOREIGN KEY (solicitante_id) REFERENCES solicitantes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'solicitacoes' ensured.\n";

    // 2. Modify `ajudas_entregas`
    echo "Checking columns in 'ajudas_entregas'...\n";
    $cols = $pdo->query("SHOW COLUMNS FROM ajudas_entregas LIKE 'solicitacao_id'")->fetchAll();
    if (count($cols) === 0) {
        $pdo->exec("ALTER TABLE ajudas_entregas ADD COLUMN solicitacao_id INT NULL AFTER solicitante_id");
        echo "Column 'solicitacao_id' added to 'ajudas_entregas'.\n";
        // Optional: Add FK if we want strictness, but let's keep it simple for now or usage ON DELETE SET NULL
        // $pdo->exec("ALTER TABLE ajudas_entregas ADD CONSTRAINT fk_entrega_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE SET NULL");
    } else {
        echo "Column 'solicitacao_id' already exists.\n";
    }

    // 3. Migrate Data
    // Check if we have data in solicitacoes
    $count = $pdo->query("SELECT COUNT(*) FROM solicitacoes")->fetchColumn();
    if ($count == 0) {
        echo "Migrating data from 'solicitantes' to 'solicitacoes'...\n";
        
        // Dynamic column detection for 'responsavel'
        $respCol = 'responsavel_cadastro'; // default
        $candidates = ['responsavel', 'responsavel_cadastro', 'servidor', 'servidor_cadastro', 'usuario_responsavel', 'usuario_cadastro', 'criado_por', 'created_by', 'usuario_nome'];
        
        $tableCols = $pdo->query("SHOW COLUMNS FROM solicitantes")->fetchAll(PDO::FETCH_COLUMN);
        $tableColsMap = array_fill_keys(array_map('strtolower', $tableCols), true);

        foreach ($candidates as $c) {
            if (isset($tableColsMap[$c])) {
                $respCol = $c;
                break;
            }
        }
        
        $hasAjuda = isset($tableColsMap['ajuda_tipo_id']);
        $hasResumo = isset($tableColsMap['resumo_caso']);
        $hasCreated = isset($tableColsMap['created_at']);

        // Build SELECT
        $fields = ["id", "nome"]; 
        if ($hasAjuda) $fields[] = "ajuda_tipo_id";
        if ($hasResumo) $fields[] = "resumo_caso";
        if ($hasCreated) $fields[] = "created_at";
        $fields[] = "$respCol as responsavel";

        $sql = "SELECT " . implode(',', $fields) . " FROM solicitantes";
        $stmt = $pdo->query($sql);
        
        $migrated = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sid = $row['id'];
            $aid = $items['ajuda_tipo_id'] ?? null;
            $res = $row['resumo_caso'] ?? null;
            $dt  = $row['created_at'] ?? date('Y-m-d H:i:s');
            $rsp = $row['responsavel'] ?? null;

             // Insert into solicitacoes
             $ins = $pdo->prepare("INSERT INTO solicitacoes (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, created_by, status) VALUES (?, ?, ?, ?, ?, 'Aberto')");
             $ins->execute([$sid, $aid, $res, $dt, $rsp]);
             $migrated++;
        }
        echo "Migrated $migrated records.\n";
    } else {
        echo "Data already migrated or table not empty. Skipped migration.\n";
    }

    $pdo->commit();
    echo "Migration completed successfully.\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
