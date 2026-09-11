<?php
require_once __DIR__ . '/orcamentos_lote_validation.php';

function lote_schema(PDO $pdo): void {
    // Tabela adicional; nenhuma tabela de negócio existente é alterada.
    $pdo->exec("CREATE TABLE IF NOT EXISTS orcamentos_importacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token CHAR(32) NOT NULL UNIQUE,
        lote_token CHAR(32) NOT NULL,
        arquivo_sha256 CHAR(64) NOT NULL UNIQUE,
        nome_original VARCHAR(255) NOT NULL,
        usuario_id INT NOT NULL,
        oficio_id INT NOT NULL UNIQUE,
        aquisicao_id INT NULL,
        dados_revisados LONGTEXT NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_importacoes_usuario (usuario_id, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function lote_storage(): string {
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: realpath(__DIR__ . '/../../');
    $configured = getenv('SO_ORCAMENTOS_STORAGE');
    $dir = $configured ?: dirname($root) . '/so-orcamentos-' . substr(hash('sha256', __DIR__), 0, 12);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento privado. Configure SO_ORCAMENTOS_STORAGE fora da pasta pública.');
    }
    $dir = realpath($dir);
    if (!$dir || !is_writable($dir) || $dir === $root || strpos($dir . '/', $root . '/') === 0) {
        throw new RuntimeException('O armazenamento de orçamentos precisa ser gravável e ficar fora da pasta pública.');
    }
    return $dir;
}

function lote_result(PDO $pdo, array $row): array {
    $stmt = $pdo->prepare('SELECT numero, status FROM oficios WHERE id = ?');
    $stmt->execute([$row['oficio_id']]);
    $oficio = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$oficio) throw new DomainException('A solicitação desta importação não está mais disponível. Consulte o suporte antes de importar novamente.');
    $stmt = $pdo->prepare('SELECT id, numero_aq FROM aquisicoes WHERE oficio_id = ? ORDER BY id LIMIT 1');
    $stmt->execute([$row['oficio_id']]);
    $aq = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['oficio_id' => (int)$row['oficio_id'], 'numero' => $oficio['numero'],
        'status' => $oficio['status'], 'aquisicao_id' => $aq ? (int)$aq['id'] : null,
        'numero_aq' => $aq ? $aq['numero_aq'] : null];
}

function lote_import(PDO $pdo, array $data, array $file, int $userId, string $nivel): array {
    $data = lote_validate($data);
    if ($data['modo'] === 'aprovar' && !in_array($nivel, ['ADMIN', 'SUPORTE'], true)) {
        throw new DomainException('Seu perfil pode enviar solicitações, mas não aprovar aquisições.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
        || !is_uploaded_file($file['tmp_name'])) throw new DomainException('Não foi possível receber o PDF.');
    $size = filesize($file['tmp_name']);
    if ($size < 5 || $size > 15 * 1024 * 1024) throw new DomainException('Cada PDF deve ter até 15 MB.');
    $name = lote_text(basename((string)($file['name'] ?? '')), 255, 'Nome do arquivo');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' || $mime !== 'application/pdf'
        || file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
        throw new DomainException('Envie um arquivo PDF válido.');
    }
    $hash = hash_file('sha256', $file['tmp_name']);
    $dir = lote_storage();
    $path = $dir . '/' . $data['token'] . '.pdf';
    $moved = false;
    // Serialize este importador. UNIQUE e retry cobrem colisões com geradores legados.
    $lockName = 'so_lote_' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new DomainException('Há outro lote em gravação. Tente novamente em alguns segundos.');
    try {
        $find = $pdo->prepare('SELECT * FROM orcamentos_importacoes WHERE token = ? OR arquivo_sha256 = ? LIMIT 1');
        $find->execute([$data['token'], $hash]);
        $previous = $find->fetch(PDO::FETCH_ASSOC);
        if ($previous) {
            if ((int)$previous['usuario_id'] !== $userId || $previous['arquivo_sha256'] !== $hash) {
                throw new DomainException('Este orçamento ou identificador já foi utilizado em outra importação.');
            }
            return lote_result($pdo, $previous) + ['reutilizado' => true];
        }
        if (file_exists($path)) throw new DomainException('Identificador já utilizado. Recarregue o lote.');
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Não foi possível guardar o PDF.');
        $moved = true;
        chmod($path, 0600);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $pdo->beginTransaction();
                foreach (['secretarias' => 'secretaria_id', 'fornecedores' => 'fornecedor_id'] as $table => $key) {
                    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ? FOR UPDATE");
                    $stmt->execute([$data[$key]]);
                    if (!$stmt->fetchColumn()) throw new DomainException('Secretaria ou fornecedor não está mais disponível.');
                }
                $numero = 'IMP-' . date('Y') . '-' . strtoupper(substr($data['token'], 0, 12));
                $status = $data['modo'] === 'aprovar' ? 'APROVADO' : 'ENVIADO';
                $url = 'orcamentos_lote.php?arquivo=' . $data['token'];
                $stmt = $pdo->prepare('INSERT INTO oficios (numero, secretaria_id, local, justificativa, resumo_itens,
                    usuario_id, fornecedor_indicado_id, valor_orcamento, arquivo_orcamento, status, criado_em)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$numero, $data['secretaria_id'], $data['local'], $data['justificativa'],
                    count($data['itens']) . ' item(ns) do orçamento ' . $name, $userId, $data['fornecedor_id'],
                    $data['total'], $url, $status]);
                $oficioId = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare('INSERT INTO oficio_anexos (oficio_id, caminho, tipo, nome_original) VALUES (?, ?, ?, ?)');
                $stmt->execute([$oficioId, $url, 'ORCAMENTO', $name]);
                $itemStmt = $pdo->prepare('INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario) VALUES (?, ?, ?, ?, ?)');
                $items = [];
                foreach ($data['itens'] as $item) {
                    $itemStmt->execute([$oficioId, $item['produto'], $item['quantidade'], $item['unidade'], $item['valor_unitario']]);
                    $item['id'] = (int)$pdo->lastInsertId();
                    $items[] = $item;
                }
                $aqId = null;
                $aqNumero = null;
                if ($data['modo'] === 'aprovar') {
                    $aqNumero = generate_aquisicao_number($pdo);
                    $stmt = $pdo->prepare('INSERT INTO aquisicoes (numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total, criado_em)
                        VALUES (?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([$aqNumero, generate_unique_code($pdo), $oficioId, $data['fornecedor_id'], $data['total']]);
                    $aqId = (int)$pdo->lastInsertId();
                    $stmt = $pdo->prepare('INSERT INTO itens_aquisicao (aquisicao_id, oficio_item_id, produto, quantidade, valor_unitario) VALUES (?, ?, ?, ?, ?)');
                    foreach ($items as $item) $stmt->execute([$aqId, $item['id'], $item['produto'], $item['quantidade'], $item['valor_unitario']]);
                }
                $stmt = $pdo->prepare('INSERT INTO orcamentos_importacoes (token, lote_token, arquivo_sha256, nome_original, usuario_id,
                    oficio_id, aquisicao_id, dados_revisados) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($json === false) throw new DomainException('Os dados revisados contêm texto inválido.');
                $stmt->execute([$data['token'], $data['lote_token'], $hash, $name, $userId, $oficioId, $aqId, $json]);
                log_action($pdo, $aqId ? 'LOTE_APROVAR_GERAR' : 'LOTE_ENVIAR',
                    "Lote {$data['lote_token']}; importação {$data['token']}; solicitação {$numero}; fornecedor {$data['fornecedor_id']}; aquisição " . ($aqNumero ?? 'aguarda aprovação'));
                $pdo->commit();
                $moved = false;
                return ['oficio_id' => $oficioId, 'numero' => $numero, 'status' => $status,
                    'aquisicao_id' => $aqId, 'numero_aq' => $aqNumero, 'reutilizado' => false];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($e instanceof PDOException && in_array((int)($e->errorInfo[1] ?? 0), [1062, 1213], true) && $attempt < 2) continue;
                throw $e;
            }
        }
    } finally {
        if ($moved && is_file($path)) unlink($path);
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
    throw new RuntimeException('Não foi possível concluir a importação.');
}
