<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

require_once __DIR__ . '/../assets/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$sid = (int)($_GET['solicitante_id'] ?? 0);
$cpf = preg_replace('/\D+/', '', (string)($_GET['cpf'] ?? ''));

if ($sid <= 0 && strlen($cpf) !== 11) {
    echo json_encode(['ok' => false, 'items' => []]);
    exit;
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
    ");
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function ensure_solicitacoes_table(PDO $pdo): void
{
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensure_solicitacoes_origem(PDO $pdo): void
{
    if (table_has_column($pdo, 'solicitacoes', 'origem')) return;

    try {
        $pdo->exec("ALTER TABLE solicitacoes ADD COLUMN origem VARCHAR(20) NULL AFTER created_by");
    } catch (Throwable $e) {
        if (!table_has_column($pdo, 'solicitacoes', 'origem')) {
            throw $e;
        }
    }
}

function ensure_cadastro_solicitacoes(PDO $pdo, int $sid, string $cpf): void
{
    ensure_solicitacoes_table($pdo);
    ensure_solicitacoes_origem($pdo);

    $hasAjuda = table_has_column($pdo, 'solicitantes', 'ajuda_tipo_id');
    $hasResumo = table_has_column($pdo, 'solicitantes', 'resumo_caso');
    if (!$hasAjuda && !$hasResumo) return;

    $hasCreated = table_has_column($pdo, 'solicitantes', 'created_at');
    $hasResp = table_has_column($pdo, 'solicitantes', 'responsavel');

    $where = [];
    $params = [];
    if ($sid > 0) {
        $where[] = 'id = :sid_lookup';
        $params[':sid_lookup'] = $sid;
    }
    if (strlen($cpf) === 11) {
        $where[] = 'cpf = :cpf_lookup';
        $params[':cpf_lookup'] = $cpf;
    }
    if (!$where) return;

    $fields = ['id'];
    $fields[] = $hasAjuda ? 'ajuda_tipo_id' : 'NULL AS ajuda_tipo_id';
    $fields[] = $hasResumo ? 'resumo_caso' : 'NULL AS resumo_caso';
    $fields[] = $hasCreated ? 'created_at' : 'NULL AS created_at';
    $fields[] = $hasResp ? 'responsavel' : 'NULL AS responsavel';

    $stmt = $pdo->prepare("SELECT " . implode(', ', $fields) . " FROM solicitantes WHERE " . implode(' OR ', $where));
    $stmt->execute($params);

    $findCadastro = $pdo->prepare("
        SELECT id
        FROM solicitacoes
        WHERE solicitante_id = :sid
          AND origem = 'cadastro'
        ORDER BY id ASC
        LIMIT 1
    ");
    $findLegacyExact = $pdo->prepare("
        SELECT id
        FROM solicitacoes
        WHERE solicitante_id = :sid
          AND COALESCE(ajuda_tipo_id, 0) = COALESCE(:aid, 0)
          AND COALESCE(TRIM(resumo_caso), '') = COALESCE(:resumo, '')
          AND DATE(COALESCE(data_solicitacao, '1000-01-01')) = DATE(COALESCE(:data_solicitacao, '1000-01-01'))
        ORDER BY id ASC
        LIMIT 1
    ");
    $findLegacyDate = $pdo->prepare("
        SELECT id
        FROM solicitacoes
        WHERE solicitante_id = :sid
          AND data_solicitacao = :data_solicitacao
        ORDER BY id ASC
        LIMIT 1
    ");
    $markCadastro = $pdo->prepare("
        UPDATE solicitacoes
           SET origem = 'cadastro'
         WHERE id = :id
         LIMIT 1
    ");
    $markDuplicadas = $pdo->prepare("
        UPDATE solicitacoes
           SET origem = 'cadastro_duplicada'
         WHERE solicitante_id = :sid
           AND id <> :cadastro_id
           AND data_solicitacao = :data_solicitacao
           AND COALESCE(origem, '') <> 'cadastro'
    ");
    $insert = $pdo->prepare("
        INSERT INTO solicitacoes
            (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, status, created_by, origem)
        VALUES
            (:sid, :aid, :resumo, :data_solicitacao, 'Aberto', :created_by, 'cadastro')
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rowSid = (int)($row['id'] ?? 0);
        $aid = ($row['ajuda_tipo_id'] ?? null) !== null ? (int)$row['ajuda_tipo_id'] : null;
        $resumo = trim((string)($row['resumo_caso'] ?? ''));
        $resumo = $resumo !== '' ? $resumo : null;
        if ($rowSid <= 0 || ($aid === null && $resumo === null)) continue;

        $dataSolicitacao = (string)($row['created_at'] ?? '');
        if ($dataSolicitacao === '' || $dataSolicitacao === '0000-00-00 00:00:00') {
            $dataSolicitacao = date('Y-m-d H:i:s');
        }

        $findCadastro->execute([':sid' => $rowSid]);
        $cadastroId = (int)($findCadastro->fetchColumn() ?: 0);
        if ($cadastroId > 0) {
            $markDuplicadas->execute([
                ':sid' => $rowSid,
                ':cadastro_id' => $cadastroId,
                ':data_solicitacao' => $dataSolicitacao,
            ]);
            continue;
        }

        $findLegacyDate->execute([
            ':sid' => $rowSid,
            ':data_solicitacao' => $dataSolicitacao,
        ]);
        $legacyId = (int)($findLegacyDate->fetchColumn() ?: 0);
        if ($legacyId > 0) {
            $markCadastro->execute([':id' => $legacyId]);
            $markDuplicadas->execute([
                ':sid' => $rowSid,
                ':cadastro_id' => $legacyId,
                ':data_solicitacao' => $dataSolicitacao,
            ]);
            continue;
        }

        $findLegacyExact->execute([
            ':sid' => $rowSid,
            ':aid' => $aid,
            ':resumo' => $resumo,
            ':data_solicitacao' => $dataSolicitacao,
        ]);
        $legacyId = (int)($findLegacyExact->fetchColumn() ?: 0);
        if ($legacyId > 0) {
            $markCadastro->execute([':id' => $legacyId]);
            $markDuplicadas->execute([
                ':sid' => $rowSid,
                ':cadastro_id' => $legacyId,
                ':data_solicitacao' => $dataSolicitacao,
            ]);
            continue;
        }

        $insert->execute([
            ':sid' => $rowSid,
            ':aid' => $aid,
            ':resumo' => $resumo,
            ':data_solicitacao' => $dataSolicitacao,
            ':created_by' => ($row['responsavel'] ?? null) ?: null,
        ]);
    }
}

try {
    if ($sid > 0 && strlen($cpf) !== 11) {
        $stCpf = $pdo->prepare("SELECT cpf FROM solicitantes WHERE id = :id LIMIT 1");
        $stCpf->execute([':id' => $sid]);
        $cpfFound = preg_replace('/\D+/', '', (string)($stCpf->fetchColumn() ?: ''));
        if (strlen($cpfFound) === 11) {
            $cpf = $cpfFound;
        }
    }

    ensure_cadastro_solicitacoes($pdo, $sid, $cpf);

    $hasSolicitacaoEntrega = table_has_column($pdo, 'ajudas_entregas', 'solicitacao_id');
    $entregaFields = $hasSolicitacaoEntrega
        ? "COALESCE(ent.entrega_id, 0) AS entrega_id,
           COALESCE(ent.entregas_count, 0) AS entregas_count,
           ent.data_entrega,
           ent.hora_entrega"
        : "0 AS entrega_id,
           0 AS entregas_count,
           NULL AS data_entrega,
           NULL AS hora_entrega";
    $entregaJoin = $hasSolicitacaoEntrega
        ? "
          LEFT JOIN (
              SELECT
                  solicitacao_id,
                  COUNT(*) AS entregas_count,
                  MAX(id) AS entrega_id,
                  MAX(data_entrega) AS data_entrega,
                  MAX(hora_entrega) AS hora_entrega
                FROM ajudas_entregas
               WHERE solicitacao_id IS NOT NULL
                 AND UPPER(entregue) = 'SIM'
               GROUP BY solicitacao_id
          ) ent ON ent.solicitacao_id = s.id"
        : "";

    // Check if table exists (graceful degradation if migration not run)
    // We try to select.
    $where = [];
    $params = [];
    if ($sid > 0) {
        $where[] = "s.solicitante_id = :sid";
        $params[':sid'] = $sid;
    }
    if (strlen($cpf) === 11) {
        $where[] = "p.cpf = :cpf";
        $params[':cpf'] = $cpf;
    }
    if (!$where) {
        echo json_encode(['ok' => false, 'items' => []]);
        exit;
    }

    $sql = "
        SELECT s.id, s.solicitante_id, p.cpf AS solicitante_cpf, p.nome AS solicitante_nome,
               s.ajuda_tipo_id, s.resumo_caso, s.data_solicitacao, s.status, s.created_by, s.origem,
               COALESCE(at.nome, '—') as ajuda_nome,
               COALESCE(at.categoria, '') as ajuda_cat,
               {$entregaFields}
          FROM solicitacoes s
          LEFT JOIN solicitantes p ON p.id = s.solicitante_id
          LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
          {$entregaJoin}
         WHERE (" . implode(' OR ', $where) . ")
           AND COALESCE(s.origem, '') <> 'cadastro_duplicada'
         ORDER BY s.data_solicitacao DESC, s.id DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($rows as &$r) {
        $dt = $r['data_solicitacao'] ?? '';
        if ($dt) {
            $ts = strtotime($dt);
            $r['data_fmt'] = date('d/m/Y H:i', $ts);
        } else {
            $r['data_fmt'] = '—';
        }
        $r['ja_atribuida'] = ((int)($r['entrega_id'] ?? 0) > 0);
        $r['entregas_count'] = (int)($r['entregas_count'] ?? 0);
    }
    unset($r);

    echo json_encode(['ok' => true, 'items' => $rows]);

} catch (Throwable $e) {
    // Maybe table doesn't exist? Return empty or error logic
    // We'll return error in msg so frontend knows
    echo json_encode(['ok' => false, 'items' => [], 'error' => $e->getMessage()]);
}
