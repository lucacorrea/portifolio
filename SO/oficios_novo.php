<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$nivel_user = strtoupper(trim($_SESSION['nivel'] ?? ''));
$page_title = "Cadastrar Nova Solicitação";

function oficio_upload_accept_attr() {
    return '.pdf,.jpg,.jpeg,.png,.doc,.docx,application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}

function oficio_allowed_upload_extensions() {
    return ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
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
        'doc' => ['application/msword', 'application/octet-stream'],
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
        throw new Exception("Arquivo {$file_name} inválido. Envie somente PDF, imagem JPG/PNG ou Word (.doc/.docx).");
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

function oficio_extract_docx_text($path) {
    if (strtolower(pathinfo((string)$path, PATHINFO_EXTENSION)) !== 'docx') {
        return '';
    }

    if (!class_exists('ZipArchive') || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        return '';
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '';
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false || trim($xml) === '') {
        return '';
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return '';
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $paragraphs = [];
    foreach ($xpath->query('//w:p') as $paragraph) {
        $text = oficio_text_from_docx_node($xpath, $paragraph);
        if ($text !== '') {
            $paragraphs[] = $text;
        }
    }

    return trim(implode("\n", $paragraphs));
}

function oficio_extract_number_from_text($text, $file_name = '') {
    $source = trim((string)$text . "\n" . (string)$file_name);

    if (preg_match('/\b(memorando|of[ií]cio)\s*(?:n[ºo°.]*)?\s*[:\-]?\s*([A-Z]{0,5}\s*\d{1,6}\s*[\/\-]\s*\d{4})/iu', $source, $match)) {
        $tipo = mb_strtoupper($match[1], 'UTF-8');
        $numero = preg_replace('/\s+/', '', $match[2]);
        $numero = str_replace('-', '/', $numero);
        return trim($tipo . ' ' . $numero);
    }

    if (preg_match('/\b(?:OF|MEMORANDO)?\s*(\d{1,6})\s*[\/\-]\s*(20\d{2})\b/iu', $source, $match)) {
        return $match[1] . '/' . $match[2];
    }

    return '';
}

function oficio_title_case_pt($value) {
    $value = mb_strtolower(trim((string)$value), 'UTF-8');
    if ($value === '') {
        return '';
    }

    return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
}

function oficio_extract_local_from_text($text, $file_name = '') {
    $source = trim((string)$text);

    if (preg_match('/\bcomunidade\s+([A-ZÁÀÂÃÉÈÊÍÌÎÓÒÔÕÚÙÛÇ][\p{L}\s\'´`.-]{2,80}?)(?=\s*\(|[,.;\n\r]|$)/iu', $source, $match)) {
        return 'Comunidade ' . oficio_title_case_pt($match[1]);
    }

    if (preg_match('/\b(?:local|comunidade|bairro|zona|ramal)\s*[:\-]\s*([^\n\r.;,]{3,90})/iu', $source, $match)) {
        return oficio_title_case_pt($match[1]);
    }

    $base = pathinfo((string)$file_name, PATHINFO_FILENAME);
    $parts = preg_split('/\s+-\s+/', $base);
    $last = trim((string)end($parts));
    if ($last !== '' && !preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $last)) {
        return oficio_title_case_pt($last);
    }

    return '';
}

function oficio_extract_justificativa_from_text($text) {
    $paragraphs = preg_split('/\R+/', trim((string)$text));
    $best = '';

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim(preg_replace('/\s+/u', ' ', $paragraph));
        if (mb_strlen($paragraph, 'UTF-8') < 50) {
            continue;
        }

        if (preg_match('/\b(solicitar|compra|aquisi[cç][aã]o|servi[cç]os?|demandas?|suprir)\b/iu', $paragraph)) {
            return $paragraph;
        }

        if ($best === '' || mb_strlen($paragraph, 'UTF-8') > mb_strlen($best, 'UTF-8')) {
            $best = $paragraph;
        }
    }

    return $best;
}

function oficio_extract_secretaria_hint_from_text($text) {
    $source = mb_strtoupper((string)$text, 'UTF-8');
    $hints = ['SEMOB', 'SEMED', 'SEMSA', 'SEMFAZ', 'SEFAZ', 'SEMAS', 'OBRAS', 'EDUCAÇÃO', 'EDUCACAO', 'SAÚDE', 'SAUDE'];

    foreach ($hints as $hint) {
        if (strpos($source, $hint) !== false) {
            return $hint;
        }
    }

    return '';
}

function oficio_extract_auto_data($text, $file_name, array $items) {
    return [
        'numero' => oficio_extract_number_from_text($text, $file_name),
        'local' => oficio_extract_local_from_text($text, $file_name),
        'justificativa' => oficio_extract_justificativa_from_text($text),
        'secretaria_hint' => oficio_extract_secretaria_hint_from_text($text),
        'resumo_itens' => oficio_build_items_summary($items) ?: '',
        'items' => $items,
    ];
}

function oficio_sanitize_posted_items($produtos) {
    if (!is_array($produtos)) {
        return [];
    }

    $items = [];
    foreach ($produtos as $idx => $produto) {
        if (!is_array($produto)) {
            continue;
        }

        $nome = oficio_sanitize_imported_product($produto['nome'] ?? '');
        if ($nome === '') {
            continue;
        }

        $quantidade = oficio_parse_decimal_value($produto['qtd'] ?? '');
        if ($quantidade === null || $quantidade <= 0) {
            throw new Exception("Informe uma quantidade válida para o item " . ((int)$idx + 1) . ".");
        }

        $valor_unitario = oficio_parse_decimal_value($produto['valor'] ?? '');
        $items[] = [
            'produto' => $nome,
            'quantidade' => $quantidade,
            'unidade' => oficio_sanitize_imported_unit($produto['unidade'] ?? 'UN'),
            'valor_unitario' => $valor_unitario !== null ? $valor_unitario : 0.0,
        ];
    }

    return $items;
}

function oficio_items_have_prices(array $items) {
    if (empty($items)) {
        return false;
    }

    foreach ($items as $item) {
        if ((float)($item['valor_unitario'] ?? 0) <= 0) {
            return false;
        }
    }

    return true;
}

function oficio_calculate_items_total(array $items) {
    $total = 0.0;

    foreach ($items as $item) {
        $total += (float)($item['quantidade'] ?? 0) * (float)($item['valor_unitario'] ?? 0);
    }

    return $total;
}

if (($_GET['ajax'] ?? '') === 'extrair_oficio') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Método inválido.");
        }

        if (!isset($_FILES['arquivo']) || trim((string)($_FILES['arquivo']['name'] ?? '')) === '') {
            throw new Exception("Envie um arquivo para leitura.");
        }

        oficio_validate_upload_error((int)($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE), (string)$_FILES['arquivo']['name']);
        $extension = oficio_validate_uploaded_file(
            (string)$_FILES['arquivo']['tmp_name'],
            (string)$_FILES['arquivo']['name'],
            (int)($_FILES['arquivo']['size'] ?? 0)
        );

        if ($extension !== 'docx') {
            echo json_encode([
                'ok' => true,
                'message' => 'Arquivo aceito para anexo. A leitura automática no servidor está disponível para Word .docx.',
                'data' => oficio_extract_auto_data('', (string)$_FILES['arquivo']['name'], []),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $text = oficio_extract_docx_text((string)$_FILES['arquivo']['tmp_name']);
        $items = oficio_extract_items_from_docx((string)$_FILES['arquivo']['tmp_name']);

        echo json_encode([
            'ok' => true,
            'message' => !empty($items)
                ? count($items) . ' item(ns) detectado(s) no Word.'
                : 'Word lido, mas nenhum item foi detectado no padrão de tabela esperado.',
            'data' => oficio_extract_auto_data($text, (string)$_FILES['arquivo']['name'], $items),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
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
            $posted_items = oficio_sanitize_posted_items($_POST['produtos'] ?? []);

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

            if (!empty($posted_items)) {
                $imported_items = $posted_items;
            } else {
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
            }

            $total_imported_items = oficio_insert_imported_items($pdo, $oficio_id, $imported_items);
            $total_items_value = oficio_calculate_items_total($imported_items);

            if ($valor_orcamento !== null && (float)$valor_orcamento > 0 && $total_items_value > 0 && abs($total_items_value - (float)$valor_orcamento) > 0.02) {
                throw new Exception("O valor total dos itens importados deve ser igual ao orçamento previsto de R$ " . number_format((float)$valor_orcamento, 2, ',', '.'));
            }

            if ($valor_orcamento === null && $total_items_value > 0) {
                $valor_orcamento = $total_items_value;
            }

            if ($total_imported_items > 0 && $resumo_itens === '') {
                $resumo_itens = oficio_build_items_summary($imported_items);
            }

            $status = ($total_imported_items > 0 && oficio_items_have_prices($imported_items))
                ? 'ENVIADO'
                : 'PENDENTE_ITENS';

            $stmt_upd = $pdo->prepare("
                UPDATE oficios
                SET arquivo_orcamento = ?, arquivo_oficio = ?, valor_orcamento = ?, resumo_itens = ?, status = ?
                WHERE id = ?
            ");
            $stmt_upd->execute([
                $arquivo_orcamento,
                $arquivo_oficio,
                $valor_orcamento,
                $resumo_itens !== '' ? $resumo_itens : null,
                $status,
                $oficio_id
            ]);

            $log_details = "Ofício {$numero_manual} cadastrado com sucesso.";
            if ($total_imported_items > 0) {
                $log_details .= " {$total_imported_items} item(ns) importado(s) do arquivo do ofício.";
            }

            log_action($pdo, "CRIAR_OFICIO", $log_details);
            $pdo->commit();

            $success_message = "Solicitação {$numero_manual} cadastrada com sucesso.";
            if ($total_imported_items > 0) {
                $success_message .= " {$total_imported_items} item(ns) foram importados do arquivo do ofício para conferência.";
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
$items_form = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produtos']) && is_array($_POST['produtos'])) {
    foreach ($_POST['produtos'] as $produto) {
        if (!is_array($produto)) {
            continue;
        }

        $nome = trim((string)($produto['nome'] ?? ''));
        if ($nome === '') {
            continue;
        }

        $items_form[] = [
            'produto' => $nome,
            'quantidade' => (string)($produto['qtd'] ?? '1'),
            'unidade' => (string)($produto['unidade'] ?? 'UN'),
            'valor_unitario' => (string)($produto['valor'] ?? ''),
        ];
    }
}

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

    .oficio-import-status {
        display: none;
        margin-top: .65rem;
        border-radius: 8px;
        padding: .7rem .85rem;
        font-size: .88rem;
        font-weight: 700;
        line-height: 1.45;
    }

    .oficio-import-status.show {
        display: block;
    }

    .oficio-import-status.success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .oficio-import-status.warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
    }

    .oficio-import-status.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .oficio-items-panel {
        display: none;
        grid-column: span 2;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: #f8fafc;
        padding: 1rem;
    }

    .oficio-items-panel.show {
        display: block;
    }

    .oficio-items-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .oficio-items-title {
        margin: 0;
        color: var(--text-dark);
        font-size: .95rem;
        font-weight: 800;
    }

    .oficio-item-row {
        display: grid;
        grid-template-columns: 52px minmax(220px, 2fr) minmax(92px, .6fr) minmax(92px, .6fr) minmax(120px, .8fr) 42px;
        gap: .75rem;
        align-items: end;
        padding: .85rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        margin-bottom: .75rem;
    }

    .oficio-item-row .form-group {
        margin: 0;
    }

    .oficio-item-seq {
        text-align: center;
        font-weight: 800;
        background: #f8fafc;
    }

    .oficio-items-empty {
        color: #64748b;
        font-size: .9rem;
        font-weight: 600;
        margin: 0 0 .75rem;
    }

    @media (max-width: 768px) {
        .solicitacao-top-grid {
            grid-template-columns: 1fr;
        }

        .solicitacao-span-2 {
            grid-column: span 1;
        }

        .oficio-items-panel {
            grid-column: span 1;
        }

        .oficio-item-row {
            grid-template-columns: 1fr;
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
                    <small class="text-muted">PDF, JPG, PNG ou Word (.doc/.docx), até <?php echo oficio_format_upload_size(oficio_upload_max_bytes()); ?> por arquivo.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Ofício de Solicitação (Opcional)</label>
                    <input type="file" name="arquivo_oficio[]" id="arquivo-oficio-input" class="form-control" accept="<?php echo oficio_upload_accept_attr(); ?>" multiple>
                    <small class="text-muted">PDF, JPG, PNG ou Word (.doc/.docx). PDF com texto selecionável e Word .docx podem preencher dados e itens automaticamente.</small>
                    <div id="oficio-import-status" class="oficio-import-status" aria-live="polite"></div>
                </div>

                <div id="oficio-items-panel" class="oficio-items-panel <?php echo !empty($items_form) ? 'show' : ''; ?>">
                    <div class="oficio-items-head">
                        <div>
                            <h4 class="oficio-items-title"><i class="fas fa-list-ul"></i> Itens detectados no ofício</h4>
                            <small class="text-muted">Revise antes de salvar. Itens com valor unitário zerado continuam pendentes para a SEMFAZ completar.</small>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" id="add-oficio-item">
                            <i class="fas fa-plus"></i> Adicionar Item
                        </button>
                    </div>

                    <p id="oficio-items-empty" class="oficio-items-empty" style="<?php echo !empty($items_form) ? 'display:none;' : ''; ?>">
                        Nenhum item carregado automaticamente.
                    </p>

                    <div id="oficio-items-container">
                        <?php foreach ($items_form as $idx => $item): ?>
                            <div class="oficio-item-row">
                                <div class="form-group">
                                    <label class="form-label">Nº</label>
                                    <input type="text" class="form-control oficio-item-seq" value="<?php echo $idx + 1; ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Item</label>
                                    <input type="text" name="produtos[<?php echo $idx; ?>][nome]" class="form-control oficio-item-name" required value="<?php echo htmlspecialchars($item['produto'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Qtd</label>
                                    <input type="number" step="0.01" min="0.01" name="produtos[<?php echo $idx; ?>][qtd]" class="form-control oficio-item-qtd" required value="<?php echo htmlspecialchars((string)$item['quantidade'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Unid.</label>
                                    <input type="text" name="produtos[<?php echo $idx; ?>][unidade]" class="form-control oficio-item-unidade" value="<?php echo htmlspecialchars((string)$item['unidade'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Valor Unit.</label>
                                    <input type="text" name="produtos[<?php echo $idx; ?>][valor]" class="form-control oficio-item-valor" placeholder="0,00" value="<?php echo htmlspecialchars((string)$item['valor_unitario'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <button type="button" class="btn btn-outline btn-sm remove-oficio-item" style="color:red; border-color:#ff000033;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.6.172/pdf.min.js"></script>
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

    const oficioForm = document.getElementById('oficio-form');
    const arquivoOficioInput = document.getElementById('arquivo-oficio-input');
    const importStatus = document.getElementById('oficio-import-status');
    const itemsPanel = document.getElementById('oficio-items-panel');
    const itemsContainer = document.getElementById('oficio-items-container');
    const itemsEmpty = document.getElementById('oficio-items-empty');
    const addItemBtn = document.getElementById('add-oficio-item');
    const pdfWorkerUrl = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.6.172/pdf.worker.min.js';

    if (window.pdfjsLib) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeKey(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^\w]+/g, ' ')
            .trim()
            .toUpperCase();
    }

    function titleCasePt(value) {
        return String(value || '')
            .toLocaleLowerCase('pt-BR')
            .replace(/(^|\s|[-'´`])(\p{L})/gu, (match, prefix, letter) => prefix + letter.toLocaleUpperCase('pt-BR'))
            .trim();
    }

    function formatInputMoneyBR(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function parseMoneyBR(value) {
        const normalized = String(value || '')
            .replace(/R\$/gi, '')
            .replace(/\s/g, '')
            .replace(/\./g, '')
            .replace(',', '.');

        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function setImportStatus(message, type = 'success') {
        if (!importStatus) return;

        importStatus.textContent = message;
        importStatus.className = `oficio-import-status show ${type}`;
    }

    function getField(name) {
        return oficioForm?.querySelector(`[name="${name}"]`) || null;
    }

    function fillIfEmpty(name, value) {
        const field = getField(name);
        if (!field || !value || String(field.value || '').trim() !== '') {
            return;
        }

        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function selectSecretariaByHint(hint, text) {
        const select = getField('secretaria_id');
        if (!select || select.value) return;

        const source = normalizeKey(`${hint || ''} ${text || ''}`);
        const aliases = [
            { keys: ['SEMOB', 'OBRAS', 'INFRAESTRUTURA'], target: ['OBRAS', 'INFRAESTRUTURA'] },
            { keys: ['SEMED', 'EDUCACAO', 'ESCOLA'], target: ['EDUCACAO'] },
            { keys: ['SEMSA', 'SAUDE'], target: ['SAUDE'] },
            { keys: ['SEMAS', 'ASSISTENCIA SOCIAL'], target: ['ASSISTENCIA SOCIAL', 'SOCIAL'] },
            { keys: ['SEMFAZ', 'SEFAZ', 'FAZENDA'], target: ['FAZENDA', 'SEFAZ', 'SEMFAZ'] }
        ];

        const options = Array.from(select.options);
        const alias = aliases.find(item => item.keys.some(key => source.includes(key)));

        for (const option of options) {
            const label = normalizeKey(option.textContent);
            if (!option.value) continue;

            if (alias && alias.target.some(target => label.includes(target))) {
                select.value = option.value;
                return;
            }

            if (source && label && (source.includes(label) || label.includes(source))) {
                select.value = option.value;
                return;
            }
        }
    }

    function extractNumberFromText(text, fileName = '') {
        const source = `${text || ''}\n${fileName || ''}`;
        const typedMatch = source.match(/\b(memorando|of[ií]cio)\s*(?:n[ºo°.]*)?\s*[:\-]?\s*([A-Z]{0,5}\s*\d{1,6}\s*[\/\-]\s*\d{4})/iu);

        if (typedMatch) {
            const tipo = typedMatch[1].toLocaleUpperCase('pt-BR');
            const numero = String(typedMatch[2]).replace(/\s+/g, '').replace('-', '/');
            return `${tipo} ${numero}`;
        }

        const looseMatch = source.match(/\b(?:OF|MEMORANDO)?\s*(\d{1,6})\s*[\/\-]\s*(20\d{2})\b/iu);
        return looseMatch ? `${looseMatch[1]}/${looseMatch[2]}` : '';
    }

    function extractLocalFromText(text, fileName = '') {
        const communityMatch = String(text || '').match(/\bcomunidade\s+([\p{L}\s'´`.-]{3,80}?)(?=\s*\(|[,.;\n\r]|$)/iu);
        if (communityMatch) {
            return `Comunidade ${titleCasePt(communityMatch[1])}`;
        }

        const localMatch = String(text || '').match(/\b(?:local|comunidade|bairro|zona|ramal)\s*[:\-]\s*([^\n\r.;,]{3,90})/iu);
        if (localMatch) {
            return titleCasePt(localMatch[1]);
        }

        const base = String(fileName || '').replace(/\.[^.]+$/, '');
        const parts = base.split(/\s+-\s+/).map(part => part.trim()).filter(Boolean);
        const last = parts[parts.length - 1] || '';
        return last && !/^\d{2}\.\d{2}\.\d{4}$/.test(last) ? titleCasePt(last) : '';
    }

    function extractJustificativaFromText(text) {
        const paragraphs = String(text || '').split(/\n+/).map(line => line.replace(/\s+/g, ' ').trim()).filter(line => line.length >= 50);
        const preferred = paragraphs.find(line => /\b(solicitar|compra|aquisi[cç][aã]o|servi[cç]os?|demandas?|suprir)\b/iu.test(line));

        if (preferred) return preferred;
        return paragraphs.sort((a, b) => b.length - a.length)[0] || '';
    }

    function extractSecretariaHintFromText(text) {
        const source = normalizeKey(text);
        const hints = ['SEMOB', 'SEMED', 'SEMSA', 'SEMFAZ', 'SEFAZ', 'SEMAS', 'OBRAS', 'EDUCACAO', 'SAUDE'];
        return hints.find(hint => source.includes(hint)) || '';
    }

    function buildItemsSummary(items) {
        if (!items.length) return '';

        const names = items.slice(0, 8).map(item => item.produto).filter(Boolean);
        return `${items.length} item(ns) detectado(s): ${names.join('; ')}${items.length > names.length ? '; ...' : ''}`;
    }

    function parseOficioItemsFromText(text) {
        const lines = String(text || '')
            .split(/\n+/)
            .map(line => line.replace(/\s+/g, ' ').trim())
            .filter(Boolean);
        const items = [];

        for (const rawLine of lines) {
            const line = rawLine
                .replace(/^\d{1,4}\s+/, '')
                .replace(/\b(QUANT\.?\s*\/\s*COMPRADA|OBS\.?)\b.*$/iu, '')
                .trim();
            const key = normalizeKey(line);

            if (
                !line ||
                key.includes('DESCRICAO') ||
                key === 'ITENS' ||
                key.startsWith('TOTAL') ||
                key.includes('CUMPRIMENTO') ||
                key.includes('ATENCIOSAMENTE')
            ) {
                continue;
            }

            const withMoney = line.match(/^(.+?)\s+([A-ZÇÁÉÍÓÚÂÊÔÃÕ]{1,12}\.?)\s+(\d+(?:[,.]\d+)?)\s+R\$\s*([\d.]+,\d{2})/iu);
            const simple = line.match(/^(.+?)\s+([A-ZÇÁÉÍÓÚÂÊÔÃÕ]{1,12}\.?)\s+(\d+(?:[,.]\d+)?)$/iu);
            const match = withMoney || simple;

            if (!match) {
                continue;
            }

            const produto = match[1].trim();
            const unidade = match[2].replace(/\.$/, '').toLocaleUpperCase('pt-BR');
            const quantidade = Number(String(match[3]).replace(',', '.'));
            const valorUnitario = withMoney ? parseMoneyBR(match[4]) : 0;

            if (!produto || !Number.isFinite(quantidade) || quantidade <= 0) {
                continue;
            }

            items.push({
                produto,
                unidade: unidade || 'UN',
                quantidade,
                valor_unitario: valorUnitario
            });
        }

        return items;
    }

    function parseAutoDataFromText(text, fileName, items = []) {
        return {
            numero: extractNumberFromText(text, fileName),
            local: extractLocalFromText(text, fileName),
            justificativa: extractJustificativaFromText(text),
            secretaria_hint: extractSecretariaHintFromText(text),
            resumo_itens: buildItemsSummary(items),
            items
        };
    }

    function createItemRow(index, item = {}) {
        const row = document.createElement('div');
        const valor = Number(item.valor_unitario || 0) > 0 ? formatInputMoneyBR(item.valor_unitario) : '';

        row.className = 'oficio-item-row';
        row.innerHTML = `
            <div class="form-group">
                <label class="form-label">Nº</label>
                <input type="text" class="form-control oficio-item-seq" value="${index + 1}" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Item</label>
                <input type="text" name="produtos[${index}][nome]" class="form-control oficio-item-name" required value="${escapeHtml(item.produto || '')}">
            </div>
            <div class="form-group">
                <label class="form-label">Qtd</label>
                <input type="number" step="0.01" min="0.01" name="produtos[${index}][qtd]" class="form-control oficio-item-qtd" required value="${escapeHtml(item.quantidade ?? 1)}">
            </div>
            <div class="form-group">
                <label class="form-label">Unid.</label>
                <input type="text" name="produtos[${index}][unidade]" class="form-control oficio-item-unidade" value="${escapeHtml(item.unidade || 'UN')}">
            </div>
            <div class="form-group">
                <label class="form-label">Valor Unit.</label>
                <input type="text" name="produtos[${index}][valor]" class="form-control oficio-item-valor" placeholder="0,00" value="${escapeHtml(valor)}">
            </div>
            <button type="button" class="btn btn-outline btn-sm remove-oficio-item" style="color:red; border-color:#ff000033;">
                <i class="fas fa-trash"></i>
            </button>
        `;

        return row;
    }

    function renumberItems() {
        if (!itemsContainer) return;

        itemsContainer.querySelectorAll('.oficio-item-row').forEach((row, index) => {
            const seq = row.querySelector('.oficio-item-seq');
            if (seq) seq.value = index + 1;

            row.querySelectorAll('input[name^="produtos["]').forEach(input => {
                input.name = input.name.replace(/produtos\[\d+\]/, `produtos[${index}]`);
            });
        });

        const hasItems = itemsContainer.querySelectorAll('.oficio-item-row').length > 0;
        if (itemsEmpty) itemsEmpty.style.display = hasItems ? 'none' : '';
        if (itemsPanel) itemsPanel.classList.toggle('show', hasItems);
    }

    function replaceItems(items) {
        if (!itemsContainer || !items.length) return;

        const hasCurrentItems = Array.from(itemsContainer.querySelectorAll('.oficio-item-name'))
            .some(input => String(input.value || '').trim() !== '');

        if (hasCurrentItems && !window.confirm('Substituir os itens atuais pelos itens detectados no ofício?')) {
            return;
        }

        itemsContainer.innerHTML = '';
        items.forEach((item, index) => {
            itemsContainer.appendChild(createItemRow(index, item));
        });
        renumberItems();
    }

    function applyAutoData(data, sourceText = '') {
        if (!data) return;

        fillIfEmpty('numero_oficio', data.numero);
        fillIfEmpty('local', data.local);
        fillIfEmpty('justificativa', data.justificativa);
        fillIfEmpty('resumo_itens', data.resumo_itens);
        selectSecretariaByHint(data.secretaria_hint, sourceText);

        if (Array.isArray(data.items) && data.items.length) {
            replaceItems(data.items);

            const total = data.items.reduce((sum, item) => sum + (Number(item.quantidade || 0) * Number(item.valor_unitario || 0)), 0);
            if (total > 0) {
                fillIfEmpty('valor_orcamento', formatInputMoneyBR(total));
            }
        }
    }

    async function extractPdfText(file) {
        if (!window.pdfjsLib) {
            throw new Error('Não foi possível carregar o leitor de PDF.');
        }

        const buffer = await file.arrayBuffer();
        const pdf = await window.pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
        const pages = [];

        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
            const page = await pdf.getPage(pageNumber);
            const content = await page.getTextContent();
            const rows = new Map();

            for (const item of content.items) {
                const value = String(item.str || '').trim();
                if (!value) continue;

                const y = Math.round(item.transform?.[5] || 0);
                const x = Number(item.transform?.[4] || 0);
                if (!rows.has(y)) rows.set(y, []);
                rows.get(y).push({ x, value });
            }

            const pageLines = Array.from(rows.entries())
                .sort((a, b) => b[0] - a[0])
                .map(([, values]) => values.sort((a, b) => a.x - b.x).map(item => item.value).join(' '));

            pages.push(pageLines.join('\n'));
        }

        return pages.join('\n');
    }

    async function extractDocxData(file) {
        const formData = new FormData();
        formData.append('arquivo', file);

        const response = await fetch('oficios_novo.php?ajax=extrair_oficio', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.message || 'Não foi possível ler o Word enviado.');
        }

        return {
            data: payload.data || {},
            message: payload.message || ''
        };
    }

    function mergeAutoData(base, next) {
        return {
            numero: base.numero || next.numero || '',
            local: base.local || next.local || '',
            justificativa: base.justificativa || next.justificativa || '',
            secretaria_hint: base.secretaria_hint || next.secretaria_hint || '',
            resumo_itens: base.resumo_itens || next.resumo_itens || '',
            items: [...(base.items || []), ...(next.items || [])]
        };
    }

    async function handleOficioFiles(files) {
        const list = Array.from(files || []);
        if (!list.length) return;

        setImportStatus('Lendo ofício e tentando preencher os dados...', 'warning');

        let merged = { numero: '', local: '', justificativa: '', secretaria_hint: '', resumo_itens: '', items: [] };
        const messages = [];

        for (const file of list) {
            const extension = String(file.name.split('.').pop() || '').toLowerCase();

            try {
                if (extension === 'docx') {
                    const result = await extractDocxData(file);
                    merged = mergeAutoData(merged, result.data || {});
                    messages.push(result.message || `${file.name}: Word lido.`);
                    continue;
                }

                if (extension === 'pdf') {
                    const text = await extractPdfText(file);
                    const items = parseOficioItemsFromText(text);
                    merged = mergeAutoData(merged, parseAutoDataFromText(text, file.name, items));
                    messages.push(items.length ? `${items.length} item(ns) detectado(s) no PDF.` : 'PDF lido, mas nenhum item foi detectado automaticamente.');
                    continue;
                }

                merged = mergeAutoData(merged, parseAutoDataFromText('', file.name, []));
                messages.push(extension === 'doc'
                    ? 'Word antigo .doc anexado. Para leitura automática, salve o arquivo como .docx.'
                    : 'Imagem anexada. Preenchimento automático de imagem exige OCR e não foi aplicado.');
            } catch (error) {
                messages.push(`${file.name}: ${error.message}`);
            }
        }

        if (!merged.resumo_itens && merged.items.length) {
            merged.resumo_itens = buildItemsSummary(merged.items);
        }

        applyAutoData(merged, JSON.stringify(merged));

        const type = merged.numero || merged.local || merged.justificativa || merged.items.length ? 'success' : 'warning';
        setImportStatus(messages.join(' '), type);
    }

    if (arquivoOficioInput) {
        arquivoOficioInput.addEventListener('change', function () {
            handleOficioFiles(this.files);
        });
    }

    if (itemsContainer) {
        itemsContainer.addEventListener('click', function (event) {
            const button = event.target.closest('.remove-oficio-item');
            if (!button) return;

            button.closest('.oficio-item-row')?.remove();
            renumberItems();
        });

        itemsContainer.addEventListener('input', function (event) {
            if (event.target.classList.contains('oficio-item-valor')) {
                event.target.value = event.target.value.replace(/[^\d,.\s]/g, '');
            }
        });

        renumberItems();
    }

    if (addItemBtn && itemsContainer) {
        addItemBtn.addEventListener('click', function () {
            itemsPanel?.classList.add('show');
            itemsContainer.appendChild(createItemRow(itemsContainer.querySelectorAll('.oficio-item-row').length, {}));
            renumberItems();
        });
    }

    oficioForm.addEventListener('submit', function () {
        atualizarDataHoraDispositivo();
    });
</script>

<?php include 'views/layout/footer.php'; ?>
