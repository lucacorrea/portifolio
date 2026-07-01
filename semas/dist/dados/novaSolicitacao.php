<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

require_once __DIR__ . '/../assets/conexao.php'; // $pdo

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro de conexão.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

$solicitante_id = (int)($_POST['solicitante_id'] ?? 0);
$ajuda_tipo_id  = (int)($_POST['ajuda_tipo_id'] ?? 0);
$resumo_caso    = trim((string)($_POST['resumo_caso'] ?? ''));
$dataSolic      = trim((string)($_POST['data_solicitacao'] ?? ''));
$fotoSolicitacao = (isset($_FILES['foto_solicitacao']) && is_array($_FILES['foto_solicitacao']))
    ? $_FILES['foto_solicitacao']
    : null;

// Nome do usuário logado
$nomeLogado =
    ((string)($_SESSION['usuario_nome'] ?? '')) ?:
    ((string)($_SESSION['nome'] ?? '')) ?:
    ((string)($_SESSION['user_nome'] ?? '')) ?:
    ((string)($_SESSION['usuario'] ?? '')) ?:
    ((string)($_SESSION['username'] ?? '')) ?:
    'Sistema';

function ensure_solicitacoes_origem(PDO $pdo): void
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

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'solicitacoes'
           AND COLUMN_NAME = 'origem'
    ");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() > 0) return;

    $pdo->exec("ALTER TABLE solicitacoes ADD COLUMN origem VARCHAR(20) NULL AFTER created_by");
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

function ensure_solicitante_documentos_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS solicitante_documentos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            solicitante_id INT UNSIGNED NOT NULL,
            solicitacao_id INT NULL,
            arquivo_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NULL,
            size_bytes BIGINT NULL,
            created_at DATETIME NULL,
            INDEX idx_docs_solicitante (solicitante_id),
            INDEX idx_docs_solicitacao (solicitacao_id),
            INDEX idx_docs_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!table_has_column($pdo, 'solicitante_documentos', 'size_bytes')) {
        $pdo->exec("ALTER TABLE solicitante_documentos ADD COLUMN size_bytes BIGINT NULL AFTER mime_type");
    }

    if (!table_has_column($pdo, 'solicitante_documentos', 'solicitacao_id')) {
        try {
            $pdo->exec("ALTER TABLE solicitante_documentos ADD COLUMN solicitacao_id INT NULL AFTER solicitante_id");
            $pdo->exec("CREATE INDEX idx_docs_solicitacao ON solicitante_documentos (solicitacao_id)");
        } catch (Throwable $e) {
            if (!table_has_column($pdo, 'solicitante_documentos', 'solicitacao_id')) {
                // Mantem compatibilidade com hospedagens sem permissao de ALTER.
            }
        }
    }
}

function save_solicitacao_foto_documento(PDO $pdo, int $solicitanteId, int $solicitacaoId, ?array $fotoFile, string $createdAt): void
{
    if (!$fotoFile || (int)($fotoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return;
    }

    if ((int)($fotoFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro no upload da foto: código ' . (int)$fotoFile['error']);
    }

    $sizeBytes = (int)($fotoFile['size'] ?? 0);
    if ($sizeBytes <= 0) {
        throw new RuntimeException('A foto enviada está vazia.');
    }
    if ($sizeBytes > 6 * 1024 * 1024) {
        throw new RuntimeException('A foto excede o tamanho máximo de 6MB.');
    }

    $tmpName = (string)($fotoFile['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Upload de foto inválido.');
    }

    $mime = strtolower((string)(@mime_content_type($tmpName) ?: ($fotoFile['type'] ?? '')));
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato de foto inválido. Use JPG, PNG ou WEBP.');
    }
    $ext = $allowed[$mime];

    $rootDir = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $docsDir = $rootDir . '/uploads/documentos';
    if (!is_dir($docsDir) && !@mkdir($docsDir, 0755, true) && !is_dir($docsDir)) {
        throw new RuntimeException('Não foi possível criar a pasta de documentos.');
    }

    $baseName = 'solicitacao_' . $solicitacaoId . '_foto_' . date('Ymd_His') . '_' . substr(sha1($solicitanteId . microtime(true)), 0, 8) . '.' . $ext;
    $destAbs = $docsDir . '/' . $baseName;
    if (!@move_uploaded_file($tmpName, $destAbs)) {
        throw new RuntimeException('Falha ao salvar a foto da solicitação.');
    }

    $relPath = 'uploads/documentos/' . $baseName;
    $originalName = 'Foto da solicitação #' . $solicitacaoId . '.' . $ext;

    try {
        if (table_has_column($pdo, 'solicitante_documentos', 'solicitacao_id')) {
            try {
                $stmtDoc = $pdo->prepare("
                    INSERT INTO solicitante_documentos
                    (solicitante_id, solicitacao_id, arquivo_path, original_name, mime_type, size_bytes, created_at)
                    VALUES (:sid, :solicitacao_id, :path, :orig, :mime, :size, :created_at)
                ");

                $stmtDoc->execute([
                    ':sid' => $solicitanteId,
                    ':solicitacao_id' => $solicitacaoId,
                    ':path' => $relPath,
                    ':orig' => $originalName,
                    ':mime' => $mime,
                    ':size' => $sizeBytes,
                    ':created_at' => $createdAt,
                ]);
                return;
            } catch (PDOException $e) {
                $stmtDoc = $pdo->prepare("
                    INSERT INTO solicitante_documentos
                    (solicitante_id, solicitacao_id, arquivo_path, original_name, mime_type, created_at)
                    VALUES (:sid, :solicitacao_id, :path, :orig, :mime, :created_at)
                ");

                $stmtDoc->execute([
                    ':sid' => $solicitanteId,
                    ':solicitacao_id' => $solicitacaoId,
                    ':path' => $relPath,
                    ':orig' => $originalName,
                    ':mime' => $mime,
                    ':created_at' => $createdAt,
                ]);
                return;
            }
        }

        try {
            $stmtDoc = $pdo->prepare("
                INSERT INTO solicitante_documentos
                (solicitante_id, arquivo_path, original_name, mime_type, size_bytes, created_at)
                VALUES (:sid, :path, :orig, :mime, :size, :created_at)
            ");

            $stmtDoc->execute([
                ':sid' => $solicitanteId,
                ':path' => $relPath,
                ':orig' => $originalName,
                ':mime' => $mime,
                ':size' => $sizeBytes,
                ':created_at' => $createdAt,
            ]);
        } catch (PDOException $e) {
            $stmtDocOld = $pdo->prepare("
                INSERT INTO solicitante_documentos
                (solicitante_id, arquivo_path, original_name, mime_type, created_at)
                VALUES (:sid, :path, :orig, :mime, :created_at)
            ");

            $stmtDocOld->execute([
                ':sid' => $solicitanteId,
                ':path' => $relPath,
                ':orig' => $originalName,
                ':mime' => $mime,
                ':created_at' => $createdAt,
            ]);
        }
    } catch (Throwable $e) {
        @unlink($destAbs);
        throw $e;
    }
}

// Validações
if ($solicitante_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID do solicitante inválido.']);
    exit;
}

if ($ajuda_tipo_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Selecione o tipo de ajuda.']);
    exit;
}

if ($resumo_caso === '') {
    echo json_encode(['ok' => false, 'msg' => 'O resumo do caso é obrigatório.']);
    exit;
}

if ($dataSolic === '') {
    echo json_encode(['ok' => false, 'msg' => 'Data da solicitação inválida.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dataSolic)) {
    echo json_encode(['ok' => false, 'msg' => 'Data da solicitação inválida.']);
    exit;
}

try {
    ensure_solicitacoes_origem($pdo);
    ensure_solicitante_documentos_table($pdo);
    $pdo->beginTransaction();

    // Confere solicitante
    $stm = $pdo->prepare("SELECT id FROM solicitantes WHERE id = ?");
    $stm->execute([$solicitante_id]);
    if (!$stm->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => 'Solicitante não encontrado.']);
        exit;
    }

    // INSERT com data/hora REAL do dispositivo
    $sql = "
        INSERT INTO solicitacoes
          (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, created_by, status, origem)
        VALUES
          (:sid, :aid, :res, :data, :usr, 'Aberto', 'adicional')
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':sid'  => $solicitante_id,
        ':aid'  => $ajuda_tipo_id,
        ':res'  => $resumo_caso,
        ':data' => $dataSolic,
        ':usr'  => $nomeLogado
    ]);

    $solicitacaoId = (int)$pdo->lastInsertId();
    save_solicitacao_foto_documento($pdo, $solicitante_id, $solicitacaoId, $fotoSolicitacao, $dataSolic);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'msg' => 'Solicitação criada com sucesso!',
        'solicitacao_id' => $solicitacaoId
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro em novaSolicitacao.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => ($e instanceof RuntimeException)
            ? $e->getMessage()
            : 'Erro ao salvar a solicitação. Tente novamente.'
    ]);
}
?>
