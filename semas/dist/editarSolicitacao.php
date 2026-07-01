<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* ===== DB ===== */
require_once __DIR__ . '/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<script>alert('Erro de conexão com o banco.');location.href='pessoasCadastradas.php';</script>";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function only_digits(?string $s): string
{
    return preg_replace('/\D+/', '', (string)$s) ?? '';
}

function fmt_cpf(?string $cpf): string
{
    $d = only_digits($cpf);
    if (strlen($d) !== 11) return $cpf ? $cpf : '—';
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

function fmt_phone(?string $f): string
{
    $d = only_digits($f);
    if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
    if (strlen($d) === 10) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
    return $f ?: '—';
}

function br_datetime_input(?string $dt): string
{
    if (!$dt) return '';
    $ts = strtotime($dt);
    if (!$ts) return '';
    return date('Y-m-d\TH:i', $ts);
}

function photo_src(?string $path): string
{
    $p = trim((string)$path);
    if ($p === '') return '';

    $p = str_replace('\\', '/', $p);
    if (preg_match('~^[a-z][a-z0-9+\-.]*://~i', $p)) return '';
    if (strpos($p, '..') !== false) return '';

    $p = ltrim($p, '/');
    if (is_file(__DIR__ . '/' . $p)) return $p;

    if (strpos($p, '/') === false) {
        $candidate = 'uploads/fotos/' . $p;
        if (is_file(__DIR__ . '/' . $candidate)) return $candidate;
    }

    return '';
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

function table_has_index(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n          FROM INFORMATION_SCHEMA.STATISTICS\n         WHERE TABLE_SCHEMA = DATABASE()\n           AND TABLE_NAME = :table_name\n           AND INDEX_NAME = :index_name\n    ");
    $stmt->execute([
        ':table_name' => $table,
        ':index_name' => $index,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function ensure_solicitacao_photo_schema(PDO $pdo): void
{
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS solicitante_documentos (\n            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            solicitante_id INT UNSIGNED NOT NULL,\n            solicitacao_id INT NULL,\n            arquivo_path VARCHAR(255) NOT NULL,\n            original_name VARCHAR(255) NOT NULL,\n            mime_type VARCHAR(120) NULL,\n            size_bytes BIGINT NULL,\n            created_at DATETIME NULL,\n            INDEX idx_docs_solicitante (solicitante_id),\n            INDEX idx_docs_solicitacao (solicitacao_id),\n            INDEX idx_docs_created (created_at)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    if (!table_has_column($pdo, 'solicitante_documentos', 'solicitacao_id')) {
        $pdo->exec("ALTER TABLE solicitante_documentos ADD COLUMN solicitacao_id INT NULL AFTER solicitante_id");
    }

    if (!table_has_index($pdo, 'solicitante_documentos', 'idx_docs_solicitacao')) {
        $pdo->exec("CREATE INDEX idx_docs_solicitacao ON solicitante_documentos (solicitacao_id)");
    }

    if (!table_has_column($pdo, 'solicitante_documentos', 'size_bytes')) {
        $pdo->exec("ALTER TABLE solicitante_documentos ADD COLUMN size_bytes BIGINT NULL AFTER mime_type");
    }
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

function find_solicitacao_photo(PDO $pdo, int $solicitanteId, int $solicitacaoId, ?string $dataSolicitacao = null): array
{
    $where = [
        "original_name LIKE :original_name",
        "arquivo_path LIKE :arquivo_path",
    ];
    $params = [
        ':solicitante_id' => $solicitanteId,
        ':original_name' => 'Foto da solicitação #' . $solicitacaoId . '.%',
        ':arquivo_path' => '%/solicitacao_' . $solicitacaoId . '_foto_%',
    ];

    if (table_has_column($pdo, 'solicitante_documentos', 'solicitacao_id')) {
        array_unshift($where, 'solicitacao_id = :solicitacao_id');
        $params[':solicitacao_id'] = $solicitacaoId;
    }

    if (trim((string)$dataSolicitacao) !== '') {
        $where[] = 'created_at = :data_solicitacao';
        $params[':data_solicitacao'] = $dataSolicitacao;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT arquivo_path, original_name, mime_type
              FROM solicitante_documentos
             WHERE solicitante_id = :solicitante_id
               AND (" . implode(' OR ', $where) . ")
               AND (
                    LOWER(COALESCE(mime_type, '')) IN ('image/jpeg', 'image/jpg', 'image/png', 'image/webp')
                    OR LOWER(arquivo_path) REGEXP '\\.(jpg|jpeg|png|webp)$'
               )
             ORDER BY id DESC
             LIMIT 1
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function find_solicitacao_photo_documents(PDO $pdo, int $solicitanteId, int $solicitacaoId, ?string $dataSolicitacao = null): array
{
    $where = [
        "original_name LIKE :original_name",
        "arquivo_path LIKE :arquivo_path",
    ];
    $params = [
        ':solicitante_id' => $solicitanteId,
        ':original_name' => 'Foto da solicitação #' . $solicitacaoId . '.%',
        ':arquivo_path' => '%/solicitacao_' . $solicitacaoId . '_foto_%',
    ];

    if (table_has_column($pdo, 'solicitante_documentos', 'solicitacao_id')) {
        array_unshift($where, 'solicitacao_id = :solicitacao_id');
        $params[':solicitacao_id'] = $solicitacaoId;
    }

    if (trim((string)$dataSolicitacao) !== '') {
        $where[] = 'created_at = :data_solicitacao';
        $params[':data_solicitacao'] = $dataSolicitacao;
    }

    $stmt = $pdo->prepare("
        SELECT id, arquivo_path
          FROM solicitante_documentos
         WHERE solicitante_id = :solicitante_id
           AND (" . implode(' OR ', $where) . ")
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function local_upload_absolute_path(?string $path): string
{
    $relative = str_replace('\\', '/', trim((string)$path));
    if ($relative === '' || strpos($relative, '..') !== false || preg_match('~^[a-z][a-z0-9+.-]*://~i', $relative)) {
        return '';
    }

    $relative = ltrim($relative, '/');
    if (strpos($relative, 'uploads/documentos/') !== 0) {
        return '';
    }

    return __DIR__ . '/' . $relative;
}

function is_solicitacao_cadastral(PDO $pdo, array $sol): bool
{
    if (strtolower((string)($sol['origem'] ?? '')) === 'cadastro') {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT ajuda_tipo_id, resumo_caso, created_at
          FROM solicitantes
         WHERE id = :id
         LIMIT 1
    ");
    $stmt->execute([':id' => (int)($sol['solicitante_id'] ?? 0)]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$base) return false;

    $baseAid = ($base['ajuda_tipo_id'] ?? null) !== null ? (int)$base['ajuda_tipo_id'] : null;
    $solAid = ($sol['ajuda_tipo_id'] ?? null) !== null ? (int)$sol['ajuda_tipo_id'] : null;
    $baseResumo = trim((string)($base['resumo_caso'] ?? ''));
    $solResumo = trim((string)($sol['resumo_caso'] ?? ''));

    $baseDate = '';
    $baseTs = strtotime((string)($base['created_at'] ?? ''));
    if ($baseTs !== false) $baseDate = date('Y-m-d', $baseTs);

    $solDate = '';
    $solTs = strtotime((string)($sol['data_solicitacao'] ?? ''));
    if ($solTs !== false) $solDate = date('Y-m-d', $solTs);

    return $baseAid === $solAid
        && $baseResumo === $solResumo
        && ($baseDate === '' || $solDate === '' || $baseDate === $solDate);
}

$modalMode = (string)($_GET['modal'] ?? $_POST['modal'] ?? '') === '1';
ensure_solicitacoes_origem($pdo);
try {
    ensure_solicitacao_photo_schema($pdo);
} catch (Throwable $e) {
    // Se o banco negar ALTER/INDEX, a edicao continua funcionando sem a foto vinculada.
}

if (empty($_SESSION['csrf_solicitacao'])) {
    $_SESSION['csrf_solicitacao'] = bin2hex(random_bytes(32));
}
$csrfSolicitacao = (string)$_SESSION['csrf_solicitacao'];
$canDeleteSolicitacao = can_delete_solicitacao();

/* ===== Dados auxiliares ===== */
$statuses = [
    'Cadastro',
    'Aberto',
    'Pendente',
    'Em andamento',
    'Concluído',
    'Cancelado',
];

/* ===== Tipos ativos ===== */
$ajudasTipos = [];
try {
    $ajudasTipos = $pdo->query("
        SELECT id, nome, categoria
        FROM ajudas_tipos
        WHERE nome IS NOT NULL AND TRIM(nome) <> ''
        ORDER BY nome ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $ajudasTipos = [];
}

/* ===== ID ===== */
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    echo "<script>alert('Solicitação inválida.');location.href='pessoasCadastradas.php';</script>";
    exit;
}

/* ===== Carregar solicitação ===== */
$sql = "
    SELECT
        s.id,
        s.solicitante_id,
        s.ajuda_tipo_id,
        s.resumo_caso,
        s.data_solicitacao,
        s.status,
        s.created_by,
        s.origem,
        p.nome AS solicitante_nome,
        p.cpf AS solicitante_cpf,
        p.telefone AS solicitante_telefone,
        p.endereco AS solicitante_endereco,
        p.numero AS solicitante_numero,
        p.foto_path AS solicitante_foto_path,
        COALESCE(b.nome, '') AS bairro_nome,
        at.nome AS ajuda_nome,
        at.categoria AS ajuda_categoria
    FROM solicitacoes s
    INNER JOIN solicitantes p ON p.id = s.solicitante_id
    LEFT JOIN bairros b ON b.id = p.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
    WHERE s.id = :id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sol) {
    echo "<script>alert('Solicitação não encontrada.');location.href='pessoasCadastradas.php';</script>";
    exit;
}

$isSolicitacaoCadastral = is_solicitacao_cadastral($pdo, $sol);
$solicitanteFotoSrc = photo_src($sol['solicitante_foto_path'] ?? '') ?: 'assets/images/user.png';
$solicitacaoFoto = find_solicitacao_photo(
    $pdo,
    (int)$sol['solicitante_id'],
    (int)$sol['id'],
    (string)($sol['data_solicitacao'] ?? '')
);
$solicitacaoFotoSrc = photo_src($solicitacaoFoto['arquivo_path'] ?? '');

/* ===== Salvar ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfPost = (string)($_POST['csrf_token'] ?? '');
    if ($csrfPost === '' || !hash_equals($csrfSolicitacao, $csrfPost)) {
        $erros = ['Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.'];
    } else {
        $erros = [];
    }

    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete' && !$erros) {
        if (!$canDeleteSolicitacao) {
            $erros[] = 'Você não tem permissão para excluir solicitações.';
        } elseif ($isSolicitacaoCadastral) {
            $erros[] = 'A solicitação inicial do cadastro não pode ser excluída.';
        } else {
            try {
                if (table_has_column($pdo, 'ajudas_entregas', 'solicitacao_id')) {
                    $deliveryStmt = $pdo->prepare("
                        SELECT COUNT(*)
                          FROM ajudas_entregas
                         WHERE solicitacao_id = :solicitacao_id
                    ");
                    $deliveryStmt->execute([':solicitacao_id' => $id]);
                    if ((int)$deliveryStmt->fetchColumn() > 0) {
                        throw new RuntimeException('Esta solicitação possui entregas vinculadas e não pode ser excluída.');
                    }
                }

                $photoDocuments = find_solicitacao_photo_documents(
                    $pdo,
                    (int)$sol['solicitante_id'],
                    (int)$sol['id'],
                    (string)($sol['data_solicitacao'] ?? '')
                );
                $photoDocumentIds = array_map(
                    static fn(array $document): int => (int)$document['id'],
                    $photoDocuments
                );

                $pdo->beginTransaction();

                if ($photoDocumentIds) {
                    $placeholders = implode(',', array_fill(0, count($photoDocumentIds), '?'));
                    $deleteDocuments = $pdo->prepare("DELETE FROM solicitante_documentos WHERE id IN ($placeholders)");
                    $deleteDocuments->execute($photoDocumentIds);
                }

                $deleteSolicitacao = $pdo->prepare("
                    DELETE FROM solicitacoes
                     WHERE id = :id
                       AND solicitante_id = :solicitante_id
                     LIMIT 1
                ");
                $deleteSolicitacao->execute([
                    ':id' => $id,
                    ':solicitante_id' => (int)$sol['solicitante_id'],
                ]);

                if ($deleteSolicitacao->rowCount() !== 1) {
                    throw new RuntimeException('Não foi possível localizar a solicitação para exclusão.');
                }

                $pdo->commit();

                foreach ($photoDocuments as $document) {
                    $absolutePath = local_upload_absolute_path($document['arquivo_path'] ?? '');
                    if ($absolutePath !== '' && is_file($absolutePath)) {
                        @unlink($absolutePath);
                    }
                }

                if ($modalMode) {
                    echo "<script>alert('Solicitação excluída com sucesso.');parent.postMessage({type:'solicitacaoExcluida'}, window.location.origin);</script>";
                } else {
                    echo "<script>alert('Solicitação excluída com sucesso.');window.location.href='pessoasCadastradas.php';</script>";
                }
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erros[] = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Erro ao excluir a solicitação.';
            }
        }
    }

    $ajudaTipoId = (int)($_POST['ajuda_tipo_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $resumo = trim((string)($_POST['resumo_caso'] ?? ''));
    $dataSolicitacao = trim((string)($_POST['data_solicitacao'] ?? ''));

    if ($action === 'save' && $ajudaTipoId <= 0) {
        $erros[] = 'Selecione o tipo de ajuda.';
    }

    if ($action === 'save' && $status === '') {
        $erros[] = 'Selecione o status.';
    }

    if ($action === 'save' && $resumo === '') {
        $erros[] = 'Informe o resumo da solicitação.';
    }

    $dataSql = null;
    if ($action === 'save' && $dataSolicitacao !== '') {
        $ts = strtotime($dataSolicitacao);
        if ($ts === false) {
            $erros[] = 'Data da solicitação inválida.';
        } else {
            $dataSql = date('Y-m-d H:i:s', $ts);
        }
    }

    if ($action === 'save' && !$erros) {
        try {
            $pdo->beginTransaction();

            $up = $pdo->prepare("
                UPDATE solicitacoes
                SET
                    ajuda_tipo_id = :ajuda_tipo_id,
                    resumo_caso = :resumo_caso,
                    data_solicitacao = :data_solicitacao,
                    status = :status
                WHERE id = :id
                LIMIT 1
            ");

            $up->execute([
                ':ajuda_tipo_id'   => $ajudaTipoId,
                ':resumo_caso'     => $resumo,
                ':data_solicitacao'=> $dataSql,
                ':status'          => $status,
                ':id'              => $id,
            ]);

            if ($isSolicitacaoCadastral) {
                $mark = $pdo->prepare("
                    UPDATE solicitacoes
                       SET origem = 'cadastro'
                     WHERE id = :id
                     LIMIT 1
                ");
                $mark->execute([':id' => $id]);

                $solicitanteFields = [
                    'ajuda_tipo_id = :ajuda_tipo_id',
                    'resumo_caso = :resumo_caso',
                ];
                $solicitanteParams = [
                    ':ajuda_tipo_id' => $ajudaTipoId,
                    ':resumo_caso' => $resumo,
                    ':solicitante_id' => (int)$sol['solicitante_id'],
                ];

                if (table_has_column($pdo, 'solicitantes', 'updated_at')) {
                    $solicitanteFields[] = 'updated_at = NOW()';
                }

                $upSolicitante = $pdo->prepare("
                    UPDATE solicitantes
                       SET " . implode(', ', $solicitanteFields) . "
                     WHERE id = :solicitante_id
                     LIMIT 1
                ");
                $upSolicitante->execute($solicitanteParams);
            }

            $pdo->commit();

            if ($modalMode) {
                echo "<script>alert('Solicitação atualizada com sucesso.');parent.postMessage({type:'solicitacaoAtualizada'}, window.location.origin);</script>";
            } else {
                echo "<script>alert('Solicitação atualizada com sucesso.');window.close();</script>";
            }
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Erro ao salvar: ' . $e->getMessage();
        }
    }

    if ($erros) {
        $sol['ajuda_tipo_id'] = $ajudaTipoId;
        $sol['status'] = $status;
        $sol['resumo_caso'] = $resumo;
        $sol['data_solicitacao'] = $dataSql ?: $dataSolicitacao;
    }
} else {
    $erros = [];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Editar Solicitação</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="shortcut icon" href="assets/images/logo/logo_pmc_2025.jpg">

    <style>
        body {
            background: #f4f7fb;
        }

        .page-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .edit-shell {
            width: 100%;
            max-width: 920px;
        }

        .edit-card {
            border: 0;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
            background: #fff;
        }

        .edit-card .card-header {
            background: linear-gradient(135deg, #25396f 0%, #3b82f6 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.25rem;
        }

        .edit-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .edit-header-row .btn-danger {
            flex-shrink: 0;
            border-radius: .65rem;
            font-weight: 700;
        }

        .edit-card .card-header h4 {
            margin: 0;
            font-weight: 800;
            font-size: 1.1rem;
        }

        .edit-card .card-header p {
            margin: .35rem 0 0;
            opacity: .9;
            font-size: .92rem;
        }

        .edit-card .card-body {
            padding: 1.5rem;
        }

        .profile-card {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            padding: 1rem;
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 1rem;
            background: #f8fafc;
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            width: 84px;
            height: 84px;
            border-radius: 16px;
            object-fit: cover;
            border: 1px solid rgba(0,0,0,.08);
            background: #fff;
            flex-shrink: 0;
        }

        .profile-content {
            flex: 1;
            min-width: 240px;
        }

        .request-photo-card {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border: 1px solid #dbe2ea;
            border-radius: 1rem;
            background: #fff;
        }

        .request-photo-card img {
            display: block;
            width: 100%;
            max-height: 420px;
            object-fit: contain;
            border-radius: .75rem;
            background: #f8fafc;
        }

        .request-photo-card .request-photo-link {
            display: block;
            max-width: 420px;
            margin: 0 auto;
        }

        .profile-content h5 {
            margin: 0 0 .35rem;
            font-weight: 800;
            color: #1f2937;
        }

        .profile-pills {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .4rem .75rem;
            border-radius: 999px;
            background: #eef2f7;
            font-size: .85rem;
            color: #334155;
        }

        .section-title {
            font-size: .95rem;
            font-weight: 800;
            color: #25396f;
            margin-bottom: .85rem;
        }

        .form-label {
            font-weight: 700;
            color: #374151;
            margin-bottom: .45rem;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: .75rem;
            border: 1px solid #dbe2ea;
        }

        textarea.form-control {
            min-height: 140px;
        }

        .help-text {
            font-size: .86rem;
            color: #6b7280;
            margin-top: .35rem;
        }

        .readonly-box {
            background: #f8fafc !important;
        }

        .actions-bar {
            display: flex;
            justify-content: center;
            gap: .75rem;
            flex-wrap: wrap;
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(0,0,0,.06);
        }

        .actions-bar .btn {
            min-width: 180px;
            border-radius: .75rem;
            font-weight: 700;
            padding: .75rem 1rem;
        }

        .alert {
            border-radius: .85rem;
        }

        @media (max-width: 768px) {
            .page-wrap {
                padding: 1rem .75rem;
                align-items: flex-start;
            }

            .edit-card .card-body {
                padding: 1rem;
            }

            .edit-header-row {
                align-items: flex-start;
            }

            .edit-header-row .btn-danger {
                padding: .55rem .7rem;
            }

            .profile-card {
                justify-content: center;
                text-align: center;
            }

            .profile-content {
                min-width: 100%;
            }

            .profile-pills {
                justify-content: center;
            }

            .actions-bar .btn {
                width: 100%;
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="edit-shell">
            <div class="card edit-card">
                <div class="card-header">
                    <div class="edit-header-row">
                        <h4 style="color: #eef0f5;"><i class="bi bi-pencil-square me-2"></i>Editar Solicitação #<?= (int)$sol['id'] ?></h4>
                        <?php if (!$modalMode && $canDeleteSolicitacao && !$isSolicitacaoCadastral): ?>
                            <form method="post" class="m-0">
                                <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfSolicitacao) ?>">
                                <input type="hidden" name="action" value="delete">
                                <?php if ($modalMode): ?>
                                    <input type="hidden" name="modal" value="1">
                                <?php endif; ?>
                                <button
                                    type="submit"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Excluir permanentemente esta solicitação e a imagem anexada? Esta ação não pode ser desfeita.');">
                                    <i class="bi bi-trash me-1"></i> Excluir
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <p>Atualize os dados da solicitação com segurança.</p>
                </div>

                <div class="card-body">

                    <?php if (!empty($erros)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($erros as $erro): ?>
                                    <li><?= h($erro) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="profile-card">
                        <img src="<?= h($solicitanteFotoSrc) ?>" alt="Foto" class="profile-avatar" onerror="this.onerror=null;this.src='assets/images/user.png';">

                        <div class="profile-content">
                            <h5><?= h($sol['solicitante_nome']) ?></h5>

                            <div class="profile-pills">
                                <span class="pill"><i class="bi bi-card-text"></i> <?= h(fmt_cpf($sol['solicitante_cpf'])) ?></span>
                                <span class="pill"><i class="bi bi-telephone"></i> <?= h(fmt_phone($sol['solicitante_telefone'])) ?></span>
                                <span class="pill"><i class="bi bi-geo-alt"></i> <?= h(($sol['solicitante_endereco'] ?: '—') . ($sol['solicitante_numero'] ? ', ' . $sol['solicitante_numero'] : '')) ?></span>
                                <span class="pill"><i class="bi bi-pin-map"></i> <?= h($sol['bairro_nome'] ?: '—') ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!$modalMode && $solicitacaoFotoSrc !== ''): ?>
                        <div class="request-photo-card">
                            <div class="section-title mb-2">Imagem adicionada na solicitação</div>
                            <a class="request-photo-link" href="<?= h($solicitacaoFotoSrc) ?>" target="_blank" rel="noopener">
                                <img
                                    src="<?= h($solicitacaoFotoSrc) ?>"
                                    alt="Imagem da solicitação #<?= (int)$sol['id'] ?>">
                            </a>
                            <div class="help-text">Clique na imagem para abrir no tamanho original.</div>
                        </div>
                    <?php endif; ?>

                    <div class="section-title">Dados da Solicitação</div>

                    <form method="post">
                        <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfSolicitacao) ?>">
                        <?php if ($modalMode): ?>
                            <input type="hidden" name="modal" value="1">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Tipo de Ajuda</label>
                                <select name="ajuda_tipo_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($ajudasTipos as $aj): ?>
                                        <option value="<?= (int)$aj['id'] ?>" <?= ((int)$sol['ajuda_tipo_id'] === (int)$aj['id']) ? 'selected' : '' ?>>
                                            <?= h($aj['nome']) ?><?= !empty($aj['categoria']) ? ' — ' . h($aj['categoria']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text">Escolha o tipo de benefício solicitado.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($statuses as $st): ?>
                                        <option value="<?= h($st) ?>" <?= ((string)$sol['status'] === (string)$st) ? 'selected' : '' ?>>
                                            <?= h($st) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Data da Solicitação</label>
                                <input
                                    type="datetime-local"
                                    name="data_solicitacao"
                                    class="form-control"
                                    value="<?= h(br_datetime_input((string)$sol['data_solicitacao'])) ?>">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Criado por</label>
                                <input
                                    type="text"
                                    class="form-control readonly-box"
                                    value="<?= h((string)($sol['created_by'] ?? '—')) ?>"
                                    readonly>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Resumo da Solicitação</label>
                                <textarea
                                    name="resumo_caso"
                                    class="form-control"
                                    rows="5"
                                    required><?= h((string)$sol['resumo_caso']) ?></textarea>
                                <div class="help-text">Descreva com clareza a necessidade apresentada pelo solicitante.</div>
                            </div>
                        </div>

                        <div class="actions-bar">
                            <button type="submit" name="action" value="save" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Salvar Alterações
                            </button>

                            <button type="button" class="btn btn-outline-secondary" onclick="<?= $modalMode ? "parent.postMessage({type:'fecharEdicaoSolicitacao'}, window.location.origin)" : 'window.close()' ?>">
                                <i class="bi bi-x-circle me-1"></i> Fechar
                            </button>
                        </div>
                    </form>

                    <?php if ($modalMode && $canDeleteSolicitacao && !$isSolicitacaoCadastral): ?>
                        <form method="post" id="formExcluirSolicitacao" class="d-none">
                            <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfSolicitacao) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="modal" value="1">
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.data?.type !== 'excluirSolicitacao') return;
            document.getElementById('formExcluirSolicitacao')?.submit();
        });
    </script>
</body>
</html>
