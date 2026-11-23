<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function modules(): array {
    static $mods = null;
    if (!$mods) {
        $mods = json_decode((string)file_get_contents(APP_ROOT . '/config/modules.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    return $mods;
}
function ensure_module(string $module): array {
    $mods = modules();
    if (!isset($mods[$module])) throw new HttpError('Módulo desconhecido.', 404);
    return $mods[$module];
}
function record_row(string $module, string $code, bool $lock = false): ?array {
    ensure_module($module);
    $sql = 'SELECT id, code, payload FROM records WHERE module=? AND code=? AND deleted_at IS NULL LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $st = db()->prepare($sql); $st->execute([$module, $code]);
    $row = $st->fetch(); return $row ?: null;
}
function record_decode(array $r): array {
    $data = json_decode($r['payload'], true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) $data = [];
    $data['id'] = $r['code'];
    return $data;
}
function collect_data(): array {
    $payload = [];
    foreach (array_keys(modules()) as $mod) $payload[$mod] = [];
    foreach (db()->query('SELECT module, code, payload FROM records WHERE deleted_at IS NULL ORDER BY id DESC') as $row) {
        if (array_key_exists($row['module'], $payload)) $payload[$row['module']][] = record_decode($row);
    }
    $payload['__milestones'] = [];
    foreach (db()->query('SELECT id, title, work, due_date, status FROM milestones ORDER BY due_date ASC, id ASC') as $row) {
        $payload['__milestones'][] = ['id' => 'MR-' . $row['id'], 'title' => $row['title'], 'work' => $row['work'], 'date' => $row['due_date'], 'status' => $row['status']];
    }
    $payload['__movements'] = [];
    foreach (db()->query('SELECT id, material_name, type, quantity, note, created_at FROM stock_movements ORDER BY id DESC LIMIT 500') as $row) {
        $payload['__movements'][] = ['id' => 'MOV-' . $row['id'], 'material' => $row['material_name'], 'tipo' => $row['type'], 'quantidade' => (float)$row['quantity'], 'observacao' => $row['note'], 'data' => $row['created_at']];
    }
    return $payload;
}
function next_code(string $module): string {
    $prefix = match($module) {
        'obras' => 'OBR-' . date('Y') . '-', 'ordens' => 'OS-', 'fiscalizacao' => 'FIS-',
        'projetos' => 'DOC-', 'orcamentos' => 'MED-', 'materiais' => 'MAT-',
        'patrimonio' => 'PAT-', 'protocolos' => date('Y') . '.',
        default => throw new HttpError('Módulo desconhecido.'),
    };
    $st = db()->prepare('SELECT last_number FROM sequences WHERE module=? FOR UPDATE');
    $st->execute([$module]); $current = $st->fetchColumn();
    if ($current === false) throw new HttpError('Sequência não instalada. Importe o SQL da estrutura.', 500);
    $next = (int)$current + 1;
    $upd = db()->prepare('UPDATE sequences SET last_number=? WHERE module=?'); $upd->execute([$next, $module]);
    $size = match ($module) { 'ordens', 'protocolos' => 6, 'fiscalizacao', 'projetos', 'orcamentos' => 4, default => 3 };
    return $prefix . str_pad((string)$next, $size, '0', STR_PAD_LEFT);
}
function validated_payload(string $module, array $previous = []): array {
    $fields = ensure_module($module)['fields'];
    $data = [];
    foreach ($fields as $field) {
        [$key, $label, $type] = $field;
        $required = (bool)($field[3] ?? false);
        $val = post_string($key, $type === 'textarea' ? 20000 : 255);
        if ($required && $val === '') throw new HttpError('Preencha o campo: ' . $label);
        if ($type === 'number') {
            if ($val === '') $number = 0;
            elseif (!is_numeric($val)) throw new HttpError('Número inválido: ' . $label);
            else $number = (float)$val;
            if (!is_finite($number) || $number < 0 || $number > 1000000000000) throw new HttpError('Valor numérico fora do limite: ' . $label);
            if ($key === 'progresso' && $number > 100) throw new HttpError('O progresso não pode ultrapassar 100%.');
            $data[$key] = $number;
        } elseif ($type === 'date') {
            if ($val !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) || !checkdate((int)substr($val, 5, 2), (int)substr($val, 8, 2), (int)substr($val, 0, 4)))) throw new HttpError('Data inválida: ' . $label);
            $data[$key] = $val;
        } elseif ($type === 'select') {
            if ($val !== '' && !in_array($val, (array)($field[4] ?? []), true)) throw new HttpError('Opção inválida: ' . $label);
            $data[$key] = $val;
        } else {
            if (mb_length($val) > ($type === 'textarea' ? 8000 : 255)) throw new HttpError('Campo muito longo: ' . $label);
            $data[$key] = $val;
        }
    }
    if (in_array($module, ['obras', 'ordens', 'fiscalizacao', 'patrimonio'], true)) {
        foreach (['lat', 'lng'] as $coord) {
            $v = post_string($coord, 30);
            if ($v === '') continue;
            if (!is_numeric($v) || !is_finite((float)$v)) throw new HttpError('Coordenada inválida.');
            $f = (float)$v;
            if ($f < ($coord === 'lat' ? -90 : -180) || $f > ($coord === 'lat' ? 90 : 180)) throw new HttpError('Coordenada fora dos limites.');
            $data[$coord] = round($f, 7);
        }
        foreach (['lat','lng'] as $c) if (!array_key_exists($c,$data) && array_key_exists($c,$previous)) $data[$c] = $previous[$c];
    }
    if (!empty($previous['demo'])) $data['demo'] = true;
    if ($module === 'materiais') $data['status'] = $data['quantidade'] < $data['minimo'] ? 'Estoque baixo' : 'Normal';
    return $data;
}
function mb_length(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
/** Pastas de anexos; em produção prioriza uma pasta fora de public_html. */
function obras_upload_dirs(): array {
    $root = realpath(APP_ROOT) ?: APP_ROOT;
    return [dirname(dirname($root)) . '/obras360-private/uploads', $root . '/storage/uploads'];
}
function obras_upload_write_dir(): string {
    foreach (obras_upload_dirs() as $dir) {
        if (is_dir($dir) || @mkdir($dir, 0700, true)) {
            if (is_writable($dir)) return $dir;
        }
    }
    throw new HttpError('Diretório de anexos indisponível.', 500);
}
function upload_attachment(string $module, string $code, int $userId): void {
    if (empty($_FILES['anexo']) || ($_FILES['anexo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
    if (!in_array($module, ['obras', 'fiscalizacao', 'projetos', 'patrimonio'], true)) throw new HttpError('Anexo indisponível para este módulo.');
    $f = $_FILES['anexo'];
    if (!is_array($f) || is_array($f['name'] ?? null) || ($f['error'] ?? 0) !== UPLOAD_ERR_OK) throw new HttpError('Falha no envio do arquivo.');
    $size = (int)($f['size'] ?? 0);
    if ($size < 1 || $size > 12 * 1024 * 1024) throw new HttpError('Anexo deve ter até 12 MB.');
    if (!is_uploaded_file((string)$f['tmp_name'])) throw new HttpError('Arquivo não reconhecido.');
    $original = basename(str_replace('\\', '/', (string)$f['name']));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$f['tmp_name']) ?: '';
    $allowed = [
        'pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];
    if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) throw new HttpError('Anexe PDF, JPG, PNG, DOC ou DOCX válido.');
    $dir = obras_upload_write_dir();
    $filename = bin2hex(random_bytes(20)) . '.' . $ext;
    $path = $dir . '/' . $filename;
    if (!move_uploaded_file((string)$f['tmp_name'], $path)) throw new HttpError('Não foi possível salvar o arquivo.', 500);
    chmod($path, 0600);
    try {
        $st = db()->prepare('INSERT INTO attachments (module, record_code, original_name, stored_name, mime, size_bytes, uploaded_by) VALUES (?,?,?,?,?,?,?)');
        $st->execute([$module, $code, substr($original, 0, 240), $filename, $mime, $size, $userId]);
    } catch (Throwable $e) { @unlink($path); throw $e; }
}
