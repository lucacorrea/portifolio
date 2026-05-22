<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$nivel_user = strtoupper(trim($_SESSION['nivel'] ?? ''));
$page_title = "Cadastrar Nova Solicitação";

function oficio_upload_accept_attr() {
    return '.pdf,.jpg,.jpeg,.png,.docx,application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}

function oficio_allowed_upload_extensions() {
    return ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
}

function oficio_upload_max_bytes() {
    return 15 * 1024 * 1024;
}

function oficio_format_upload_size($bytes) {
    return number_format($bytes / 1024 / 1024, 0, ',', '.') . ' MB';
}

function oficio_validate_upload_error($error, $file_name) {
    if ($error === UPLOAD_ERR_OK) {
        return;
    }

    $safe_name = $file_name !== '' ? $file_name : 'arquivo';
    $messages = [
        UPLOAD_ERR_INI_SIZE => "O arquivo {$safe_name} ultrapassa o limite configurado no servidor.",
        UPLOAD_ERR_FORM_SIZE => "O arquivo {$safe_name} ultrapassa o limite permitido.",
        UPLOAD_ERR_PARTIAL => "O upload do arquivo {$safe_name} foi enviado parcialmente.",
        UPLOAD_ERR_NO_FILE => "Nenhum arquivo foi enviado.",
        UPLOAD_ERR_NO_TMP_DIR => "Diretório temporário de upload indisponível.",
        UPLOAD_ERR_CANT_WRITE => "Não foi possível gravar o arquivo {$safe_name} no servidor.",
        UPLOAD_ERR_EXTENSION => "Uma extensão do PHP bloqueou o upload do arquivo {$safe_name}.",
    ];

    throw new Exception($messages[$error] ?? "Erro ao enviar o arquivo {$safe_name}.");
}

function oficio_detect_upload_mime($tmp_name) {
    if (!function_exists('finfo_open') || !is_file($tmp_name)) {
        return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return '';
    }

    $mime = (string)finfo_file($finfo, $tmp_name);
    finfo_close($finfo);

    return strtolower(trim($mime));
}

function oficio_mime_allowed_for_extension($extension, $mime) {
    if ($mime === '') {
        return true;
    }

    $allowed = [
        'pdf' => ['application/pdf', 'application/octet-stream'],
        'jpg' => ['image/jpeg', 'image/pjpeg', 'application/octet-stream'],
        'jpeg' => ['image/jpeg', 'image/pjpeg', 'application/octet-stream'],
        'png' => ['image/png', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
    ];

    return isset($allowed[$extension]) && in_array($mime, $allowed[$extension], true);
}

function oficio_validate_uploaded_file($tmp_name, $file_name, $file_size) {
    $extension = strtolower(pathinfo((string)$file_name, PATHINFO_EXTENSION));
    $allowed_extensions = oficio_allowed_upload_extensions();

    if (!in_array($extension, $allowed_extensions, true)) {
        throw new Exception("Arquivo {$file_name} inválido. Envie somente PDF, imagem JPG/PNG ou Word (.docx).");
    }

    if ((int)$file_size <= 0) {
        throw new Exception("O arquivo {$file_name} está vazio.");
    }

    if ((int)$file_size > oficio_upload_max_bytes()) {
        throw new Exception("O arquivo {$file_name} ultrapassa o limite de " . oficio_format_upload_size(oficio_upload_max_bytes()) . ".");
    }

    $mime = oficio_detect_upload_mime($tmp_name);
    if (!oficio_mime_allowed_for_extension($extension, $mime)) {
        throw new Exception("O tipo do arquivo {$file_name} não corresponde à extensão informada.");
    }

    return $extension;
}

function oficio_unique_upload_suffix() {
    if (function_exists('random_bytes')) {
        try {
            return bin2hex(random_bytes(8));
        } catch (Exception $e) {
            // Fallback abaixo.
        }
    }

    return str_replace('.', '', uniqid('', true));
}

function oficio_normalize_files_array($files) {
    $normalized = [];
    $names = is_array($files['name'] ?? null) ? $files['name'] : [$files['name'] ?? ''];
    $total = count($names);

    for ($i = 0; $i < $total; $i++) {
        $normalized[] = [
            'name' => $names[$i] ?? '',
            'type' => is_array($files['type'] ?? null) ? ($files['type'][$i] ?? '') : ($files['type'] ?? ''),
            'tmp_name' => is_array($files['tmp_name'] ?? null) ? ($files['tmp_name'][$i] ?? '') : ($files['tmp_name'] ?? ''),
            'error' => is_array($files['error'] ?? null) ? (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) : (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => is_array($files['size'] ?? null) ? (int)($files['size'][$i] ?? 0) : (int)($files['size'] ?? 0),
        ];
    }

    return $normalized;
}

function oficio_handle_multiple_uploads($filesKey, $targetDir, $prefix, $tipo, $oficio_id, $pdo, &$saved_files, &$moved_paths) {
    if (!isset($_FILES[$filesKey])) {
        return null;
    }

    $files = oficio_normalize_files_array($_FILES[$filesKey]);
    $files = array_values(array_filter($files, function ($file) {
        return trim((string)($file['name'] ?? '')) !== '';
    }));

    if (empty($files)) {
        return null;
    }

    $first_file_path = null;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        throw new Exception("Não foi possível criar o diretório de upload.");
    }

    foreach ($files as $file) {
        $file_name = (string)$file['name'];
        oficio_validate_upload_error((int)$file['error'], $file_name);

        $extension = oficio_validate_uploaded_file(
            (string)$file['tmp_name'],
            $file_name,
            (int)$file['size']
        );

        $new_name = $prefix . "_" . date("Ymd_His") . "_" . oficio_unique_upload_suffix() . "." . $extension;
        $target_path = $targetDir . $new_name;

        if (!move_uploaded_file((string)$file['tmp_name'], $target_path)) {
            throw new Exception("Não foi possível salvar o arquivo {$file_name}.");
        }

        $moved_paths[] = $target_path;
        if ($first_file_path === null) {
            $first_file_path = $target_path;
        }

        $stmt_anexo = $pdo->prepare("
            INSERT INTO oficio_anexos (oficio_id, caminho, tipo, nome_original)
            VALUES (?, ?, ?, ?)
        ");
        $stmt_anexo->execute([$oficio_id, $target_path, $tipo, $file_name]);

        $saved_files[] = [
            'caminho' => $target_path,
            'tipo' => $tipo,
            'extensao' => $extension,
            'nome_original' => $file_name,
        ];
    }

    return $first_file_path;
}

function oficio_text_from_docx_node(DOMXPath $xpath, DOMNode $node) {
    $parts = [];
    foreach ($xpath->query('.//w:t', $node) as $text_node) {
        $parts[] = $text_node->textContent;
    }

    return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));
}

function oficio_normalize_key($value) {
    $value = mb_strtoupper(trim((string)$value), 'UTF-8');
    $value = strtr($value, [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C',
    ]);

    return trim(preg_replace('/[^A-Z0-9]+/', ' ', $value));
}

function oficio_parse_decimal_value($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $value = str_ireplace('R$', '', $value);
    $value = preg_replace('/[^\d,.\-]/', '', $value);

    if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
        return null;
    }

    if (strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
        $value = str_replace('.', '', $value);
    }

    return is_numeric($value) ? (float)$value : null;
}

function oficio_detect_docx_item_header(array $cells) {
    $map = [
        'produto' => null,
        'unidade' => null,
        'quantidade' => null,
        'valor_unitario' => null,
    ];

    foreach ($cells as $index => $cell) {
        $key = oficio_normalize_key($cell);

        if ($key === '') {
            continue;
        }

        if ($map['produto'] === null && (
            strpos($key, 'DESCRICAO') !== false ||
            strpos($key, 'PRODUTO') !== false ||
            strpos($key, 'MATERIAL') !== false ||
            strpos($key, 'SERVICO') !== false
        )) {
            $map['produto'] = $index;
        }

        if ($map['unidade'] === null && preg_match('/\b(UNID|UNIDADE|UND|UN)\b/', $key)) {
            $map['unidade'] = $index;
        }

        if ($map['quantidade'] === null && (
            strpos($key, 'QUANT') !== false ||
            preg_match('/\bQTD\b/', $key)
        ) && strpos($key, 'COMPRAD') === false) {
            $map['quantidade'] = $index;
        }

        if ($map['valor_unitario'] === null && (
            strpos($key, 'VALOR UNIT') !== false ||
            strpos($key, 'V UNIT') !== false ||
            strpos($key, 'PRECO UNIT') !== false
        )) {
            $map['valor_unitario'] = $index;
        }
    }

    $all = oficio_normalize_key(implode(' ', $cells));
    if ($map['produto'] === null && strpos($all, 'ITENS') !== false && count($cells) >= 4) {
        $map['produto'] = 1;
    }

    if ($map['unidade'] === null && count($cells) >= 4) {
        $map['unidade'] = 2;
    }

    if ($map['quantidade'] === null && count($cells) >= 4 && strpos($all, 'QUANT') !== false) {
        $map['quantidade'] = 3;
    }

    if ($map['produto'] === null || $map['quantidade'] === null) {
        return null;
    }

    return $map;
}

function oficio_sanitize_imported_unit($unit) {
    $unit = trim(preg_replace('/\s+/u', ' ', (string)$unit));
    $unit = trim($unit, " \t\n\r\0\x0B.");

    if ($unit === '') {
        return 'UN';
    }

    return mb_substr(mb_strtoupper($unit, 'UTF-8'), 0, 20, 'UTF-8');
}

function oficio_sanitize_imported_product($product) {
    $product = trim(preg_replace('/\s+/u', ' ', (string)$product));
    $product = trim($product, " \t\n\r\0\x0B.-");

    return mb_substr($product, 0, 255, 'UTF-8');
}

function oficio_extract_items_from_docx($path) {
    if (strtolower(pathinfo((string)$path, PATHINFO_EXTENSION)) !== 'docx') {
        return [];
    }

    if (!class_exists('ZipArchive')) {
        throw new Exception("O PHP não possui ZipArchive habilitado para ler itens de arquivos DOCX.");
    }

    if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        throw new Exception("O PHP não possui DOM habilitado para ler itens de arquivos DOCX.");
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception("Não foi possível abrir o DOCX para importar itens.");
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false || trim($xml) === '') {
        return [];
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        throw new Exception("Não foi possível ler a estrutura do DOCX para importar itens.");
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $items = [];

    foreach ($xpath->query('//w:tbl') as $table) {
        $current_header = null;

        foreach ($xpath->query('./w:tr', $table) as $row) {
            $cells = [];
            foreach ($xpath->query('./w:tc', $row) as $cell) {
                $cells[] = oficio_text_from_docx_node($xpath, $cell);
            }

            if (empty(array_filter($cells, function ($cell) {
                return trim((string)$cell) !== '';
            }))) {
                continue;
            }

            $header = oficio_detect_docx_item_header($cells);
            if ($header !== null) {
                $current_header = $header;
                continue;
            }

            if ($current_header === null) {
                continue;
            }

            $produto = oficio_sanitize_imported_product($cells[$current_header['produto']] ?? '');
            $quantidade = oficio_parse_decimal_value($cells[$current_header['quantidade']] ?? '');
            $unidade = oficio_sanitize_imported_unit($cells[$current_header['unidade']] ?? 'UN');
            $valor_unitario = 0.0;

            if ($current_header['valor_unitario'] !== null) {
                $valor_doc = oficio_parse_decimal_value($cells[$current_header['valor_unitario']] ?? '');
                $valor_unitario = $valor_doc !== null ? $valor_doc : 0.0;
            }

            $produto_key = oficio_normalize_key($produto);
            if ($produto === '' || $quantidade === null || $quantidade <= 0) {
                continue;
            }

            if (
                strpos($produto_key, 'DESCRICAO') !== false ||
                strpos($produto_key, 'TOTAL') === 0 ||
                $produto_key === 'ITENS'
            ) {
                continue;
            }

            $items[] = [
                'produto' => $produto,
                'quantidade' => $quantidade,
                'unidade' => $unidade,
                'valor_unitario' => $valor_unitario,
            ];
        }
    }

    return $items;
}

function oficio_insert_imported_items($pdo, $oficio_id, array $items) {
    if (empty($items)) {
        return 0;
    }

    $stmt_item = $pdo->prepare("
        INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario)
        VALUES (?, ?, ?, ?, ?)
    ");

    $count = 0;
    foreach ($items as $item) {
        $stmt_item->execute([
            $oficio_id,
            $item['produto'],
            $item['quantidade'],
            $item['unidade'],
            $item['valor_unitario'],
        ]);
        $count++;
    }

    return $count;
}

function oficio_build_items_summary(array $items) {
    if (empty($items)) {
        return null;
    }

    $names = [];
    foreach (array_slice($items, 0, 8) as $item) {
        $names[] = $item['produto'];
    }

    $summary = count($items) . " item(ns) importado(s) do Word: " . implode('; ', $names);
    if (count($items) > count($names)) {
        $summary .= '; ...';
    }

    return $summary;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secretaria_id    = $_POST['secretaria_id'] ?? '';
    $local            = trim($_POST['local'] ?? '');
    $resumo_itens     = trim($_POST['resumo_itens'] ?? '');
    $justificativa    = trim($_POST['justificativa'] ?? '');
    $valor_orcamento  = !empty($_POST['valor_orcamento'])
        ? str_replace(['.', ','], ['', '.'], $_POST['valor_orcamento'])
        : null;
    $numero_manual    = isset($_POST['numero_oficio']) ? mb_strtoupper(trim($_POST['numero_oficio']), 'UTF-8') : null;
    $criado_em_device = trim($_POST['criado_em_device'] ?? '');

    if (empty($justificativa)) {
        $error = "O campo Justificativa é obrigatório.";
    } elseif (empty($numero_manual)) {
        $error = "Informe o número do ofício.";
    } elseif (empty($secretaria_id)) {
        $error = "Selecione a secretaria solicitante.";
    } elseif (empty($local)) {
        $error = "Informe o local da solicitação.";
    } elseif (empty($criado_em_device)) {
        $error = "Não foi possível capturar a data e hora do dispositivo. Atualize a página e tente novamente.";
    } else {
        try {
            $pdo->beginTransaction();
            $saved_files = [];
            $moved_paths = [];
            $imported_items = [];
            $import_warnings = [];

            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $criado_em_device);
            if (!$dt || $dt->format('Y-m-d H:i:s') !== $criado_em_device) {
                throw new Exception("Data/hora do dispositivo inválida.");
            }

            $stmt_check = $pdo->prepare("SELECT id FROM oficios WHERE numero = ?");
            $stmt_check->execute([$numero_manual]);
            if ($stmt_check->fetch()) {
                throw new Exception("O número de ofício '{$numero_manual}' já está cadastrado.");
            }

            $arquivo_orcamento = null;
            $arquivo_oficio    = null;
            $status = 'PENDENTE_ITENS';

            $stmt = $pdo->prepare("
                INSERT INTO oficios
                    (numero, secretaria_id, local, justificativa, resumo_itens, usuario_id, valor_orcamento, arquivo_orcamento, status, criado_em)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $numero_manual,
                $secretaria_id,
                $local,
                $justificativa,
                $resumo_itens !== '' ? $resumo_itens : null,
                $_SESSION['user_id'],
                $valor_orcamento,
                $arquivo_orcamento,
                $status,
                $criado_em_device
            ]);

            $oficio_id = $pdo->lastInsertId();

            $arquivo_orcamento = oficio_handle_multiple_uploads(
                'orcamento',
                'assets/uploads/orcamentos/',
                'ORC',
                'ORCAMENTO',
                $oficio_id,
                $pdo,
                $saved_files,
                $moved_paths
            );

            $arquivo_oficio = oficio_handle_multiple_uploads(
                'arquivo_oficio',
                'assets/uploads/oficios/',
                'OFI',
                'OFICIO',
                $oficio_id,
                $pdo,
                $saved_files,
                $moved_paths
            );

            foreach ($saved_files as $saved_file) {
                if ($saved_file['tipo'] !== 'OFICIO' || $saved_file['extensao'] !== 'docx') {
                    continue;
                }

                try {
                    $items_from_docx = oficio_extract_items_from_docx($saved_file['caminho']);
                    if (!empty($items_from_docx)) {
                        $imported_items = array_merge($imported_items, $items_from_docx);
                    } else {
                        $import_warnings[] = "O arquivo {$saved_file['nome_original']} foi anexado, mas não encontrei tabela de itens no padrão esperado.";
                    }
                } catch (Throwable $docxError) {
                    $import_warnings[] = "O arquivo {$saved_file['nome_original']} foi anexado, mas os itens não puderam ser importados automaticamente.";
                }
            }

            $total_imported_items = oficio_insert_imported_items($pdo, $oficio_id, $imported_items);
            if ($total_imported_items > 0 && $resumo_itens === '') {
                $resumo_itens = oficio_build_items_summary($imported_items);
            }

            $stmt_upd = $pdo->prepare("
                UPDATE oficios
                SET arquivo_orcamento = ?, arquivo_oficio = ?, valor_orcamento = ?, resumo_itens = ?
                WHERE id = ?
            ");
            $stmt_upd->execute([
                $arquivo_orcamento,
                $arquivo_oficio,
                $valor_orcamento,
                $resumo_itens !== '' ? $resumo_itens : null,
                $oficio_id
            ]);

            $log_details = "Ofício {$numero_manual} cadastrado com sucesso.";
            if ($total_imported_items > 0) {
                $log_details .= " {$total_imported_items} item(ns) importado(s) do Word.";
            }

            log_action($pdo, "CRIAR_OFICIO", $log_details);
            $pdo->commit();

            $success_message = "Solicitação {$numero_manual} cadastrada com sucesso.";
            if ($total_imported_items > 0) {
                $success_message .= " {$total_imported_items} item(ns) foram importados do Word para conferência.";
            } elseif (!empty($import_warnings)) {
                $success_message .= " " . implode(' ', $import_warnings);
            }

            flash_message('success', $success_message);
            header("Location: oficios_visualizar.php?id={$oficio_id}");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (!empty($moved_paths)) {
                foreach ($moved_paths as $moved_path) {
                    if (is_file($moved_path)) {
                        @unlink($moved_path);
                    }
                }
            }
            $error = "Erro ao cadastrar: " . $e->getMessage();
        }
    }
}

$secretarias = $pdo->query("SELECT * FROM secretarias ORDER BY nome")->fetchAll();

include 'views/layout/header.php';
?>

<style>
    .solicitacao-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .solicitacao-top-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .solicitacao-span-2 {
        grid-column: span 2;
    }

    .solicitacao-actions {
        margin-top: 2rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
        text-align: right;
    }

    .btn-salvar-solicitacao {
        padding: 0.75rem 2rem;
    }

    .device-time-box {
        margin-bottom: 1.5rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 0.95rem;
        color: #334155;
    }

    .device-time-box strong {
        color: #0f172a;
    }

    @media (max-width: 768px) {
        .solicitacao-top-grid {
            grid-template-columns: 1fr;
        }

        .solicitacao-span-2 {
            grid-column: span 1;
        }

        .solicitacao-actions {
            text-align: center;
        }

        .btn-salvar-solicitacao {
            width: 100%;
        }
    }
</style>

<div class="card">
    <div class="card-body">
        <div class="solicitacao-header">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem; margin: 0;">
                <i class="fas fa-edit" style="margin-right: 10px; color: var(--primary);"></i>
                Formulário de Solicitação (Casa Civil)
            </h3>
            <a href="oficios_lista.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="device-time-box">
            <strong>Data/hora do dispositivo:</strong>
            <span id="preview-datahora-dispositivo">Carregando...</span>
        </div>

        <form action="" method="POST" id="oficio-form" enctype="multipart/form-data">
            <input type="hidden" name="criado_em_device" id="criado_em_device" value="">

            <div class="solicitacao-top-grid">

                <div class="form-group">
                    <label class="form-label">Número do Ofício <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="numero_oficio"
                        class="form-control"
                        placeholder="Ex: OF-2026-01"
                        oninput="this.value = this.value.toUpperCase()"
                        value="<?php echo htmlspecialchars($_POST['numero_oficio'] ?? ''); ?>"
                        required
                    >
                    <small class="text-muted">Informe o número do processo físico ou ofício.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Secretaria Solicitante <span style="color:red">*</span></label>
                    <select name="secretaria_id" class="form-control" required>
                        <option value="">Selecione a Secretaria...</option>
                        <?php foreach ($secretarias as $sec): ?>
                            <option
                                value="<?php echo $sec['id']; ?>"
                                <?php echo (isset($_POST['secretaria_id']) && $_POST['secretaria_id'] == $sec['id']) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($sec['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Local <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="local"
                        class="form-control"
                        placeholder="Ex: Almoxarifado Central, Zona Rural, Unidade de Saúde..."
                        value="<?php echo htmlspecialchars($_POST['local'] ?? ''); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Valor do Orçamento (Opcional)</label>
                    <input
                        type="text"
                        name="valor_orcamento"
                        class="form-control"
                        placeholder="0,00"
                        onkeyup="this.value = this.value.replace(/[^\d,]/g, '')"
                        value="<?php echo htmlspecialchars($_POST['valor_orcamento'] ?? ''); ?>"
                    >
                </div>

                <div class="form-group solicitacao-span-2">
                    <label class="form-label">Resumo dos Itens a Cadastrar</label>
                    <textarea
                        name="resumo_itens"
                        class="form-control"
                        placeholder="Ex: material de expediente, gêneros alimentícios, equipamentos, serviços ou observações sobre os itens que serão detalhados depois..."
                        rows="3"
                    ><?php echo htmlspecialchars($_POST['resumo_itens'] ?? ''); ?></textarea>
                    <small class="text-muted">Use este campo para registrar uma prévia dos itens antes da atribuição detalhada.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Arquivo do Orçamento (Opcional)</label>
                    <input type="file" name="orcamento[]" class="form-control" accept="<?php echo oficio_upload_accept_attr(); ?>" multiple>
                    <small class="text-muted">PDF, JPG, PNG ou Word (.docx), até <?php echo oficio_format_upload_size(oficio_upload_max_bytes()); ?> por arquivo.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Ofício de Solicitação (Opcional)</label>
                    <input type="file" name="arquivo_oficio[]" class="form-control" accept="<?php echo oficio_upload_accept_attr(); ?>" multiple>
                    <small class="text-muted">PDF, JPG, PNG ou Word (.docx). Arquivos .docx com tabela de itens serão importados para conferência.</small>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label">Justificativa / Finalidade <span style="color:red">*</span></label>
                <textarea
                    name="justificativa"
                    class="form-control"
                    placeholder="Descreva detalhadamente a necessidade da solicitação..."
                    rows="4"
                    required
                ><?php echo htmlspecialchars($_POST['justificativa'] ?? ''); ?></textarea>
            </div>

            <div class="alert alert-info" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <i class="fas fa-info-circle" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>Nota:</strong> Este formulário é destinado ao registro inicial. Se o ofício em Word (.docx) tiver tabela com descrição, unidade e quantidade, os itens serão pré-cadastrados para conferência posterior.
                </div>
            </div>

            <div class="solicitacao-actions">
                <button type="submit" class="btn btn-primary btn-salvar-solicitacao">
                    <i class="fas fa-save"></i> Salvar Solicitação
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function getDeviceDateTimeForMysql() {
        const now = new Date();
        return now.getFullYear() + '-' +
            pad(now.getMonth() + 1) + '-' +
            pad(now.getDate()) + ' ' +
            pad(now.getHours()) + ':' +
            pad(now.getMinutes()) + ':' +
            pad(now.getSeconds());
    }

    function getDeviceDateTimeForPreview() {
        const now = new Date();
        return pad(now.getDate()) + '/' +
            pad(now.getMonth() + 1) + '/' +
            now.getFullYear() + ' ' +
            pad(now.getHours()) + ':' +
            pad(now.getMinutes()) + ':' +
            pad(now.getSeconds());
    }

    function atualizarDataHoraDispositivo() {
        const mysqlDatetime = getDeviceDateTimeForMysql();
        const previewDatetime = getDeviceDateTimeForPreview();

        const inputHidden = document.getElementById('criado_em_device');
        const preview = document.getElementById('preview-datahora-dispositivo');

        if (inputHidden) inputHidden.value = mysqlDatetime;
        if (preview) preview.textContent = previewDatetime;
    }

    atualizarDataHoraDispositivo();
    setInterval(atualizarDataHoraDispositivo, 1000);

    document.getElementById('oficio-form').addEventListener('submit', function () {
        atualizarDataHoraDispositivo();
    });
</script>

<?php include 'views/layout/footer.php'; ?>
