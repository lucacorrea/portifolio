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
    // Nunca gravar novos anexos em public_html. A leitura legada
    // permanece disponível em download.php para arquivos antigos.
    $dir = obras_upload_dirs()[0];
    if ((is_dir($dir) || @mkdir($dir, 0700, true)) && is_writable($dir)) return $dir;
    throw new HttpError('Crie a pasta privada obras360-private/uploads com permissão de gravação.', 500);
}
/**
 * Ajusta imagens de anexos para até 30 KiB. Imagens pequenas são mantidas
 * byte a byte; imagens maiores podem perder resolução/detalhes visuais.
 * Se o limite não puder ser atingido dentro dos parâmetros mínimos, rejeita
 * o anexo em vez de guardar um arquivo maior ou muito degradado.
 *
 * @return array{path:string,extension:string,mime:string,size:int,temporary:bool}
 */
function obras_imagem_30kb(string $source, string $extension): array {
    $limit = 30 * 1024;
    $types = ['jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp'];
    $extension = strtolower($extension);
    if (!isset($types[$extension])) throw new HttpError('Formato de imagem não suportado.');
    $info = @getimagesize($source);
    if (!is_array($info) || ($info['mime'] ?? '') !== $types[$extension]) {
        throw new HttpError('Imagem inválida ou extensão incompatível com o conteúdo.');
    }
    $width = (int)$info[0]; $height = (int)$info[1];
    if ($width < 1 || $height < 1 || $width * $height > 16000000) {
        throw new HttpError('Imagem com dimensões inválidas ou muito grandes. Limite de 16 megapixels.');
    }
    $originalSize = filesize($source);
    if ($originalSize === false) throw new HttpError('Não foi possível ler o tamanho da imagem.');
    if ($originalSize <= $limit) {
        // Sem conversão: preserva integralmente bytes, tamanho e formato.
        return ['path'=>$source,'extension'=>$extension,'mime'=>$types[$extension],
            'size'=>(int)$originalSize,'temporary'=>false];
    }
    if (!extension_loaded('gd') || !function_exists('imagewebp') ||
        !function_exists('imagecreatetruecolor')) {
        throw new HttpError('A compressão de imagens acima de 30 KB exige a extensão PHP GD com WebP habilitada na hospedagem.',500);
    }
    $readers = ['jpg'=>'imagecreatefromjpeg','jpeg'=>'imagecreatefromjpeg',
        'png'=>'imagecreatefrompng','webp'=>'imagecreatefromwebp'];
    $reader = $readers[$extension];
    if (!function_exists($reader)) throw new HttpError('O servidor não suporta a leitura deste formato de imagem.',500);
    $original = @$reader($source);
    if (!$original instanceof GdImage) throw new HttpError('Não foi possível decodificar a imagem.');
    // Fotos de celular podem trazer a orientação no EXIF. Preserva a posição visual.
    if (in_array($extension, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
        $exif = @exif_read_data($source);
        $orientation = is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1;
        $degrees = match ($orientation) { 3 => 180, 6 => 270, 8 => 90, default => 0 };
        if ($degrees !== 0 && function_exists('imagerotate')) {
            $rotated = @imagerotate($original, $degrees, 0);
            if ($rotated instanceof GdImage) {
                imagedestroy($original);
                $original = $rotated;
                $width = imagesx($original); $height = imagesy($original);
            }
        }
        if (in_array($orientation,[2,4],true) && function_exists('imageflip')) {
            imageflip($original, $orientation === 2 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }
    }
    $tmp = tempnam(sys_get_temp_dir(), 'obras_img_');
    if ($tmp === false) { imagedestroy($original); throw new HttpError('Não foi possível criar arquivo temporário de imagem.',500); }
    $saved = false;
    try {
        // Alta qualidade primeiro; reduz gradualmente até o teto. Não amplia imagens.
        $lastDimensions = '';
        foreach ([1600,1440,1280,1152,1024,900,800,700,600,512,448,384] as $maxSide) {
            $scale = min(1.0, $maxSide / max($width,$height));
            $newW = max(1,(int)round($width*$scale));
            $newH = max(1,(int)round($height*$scale));
            if ($scale < 1 && max($newW,$newH) < 384 && max($width,$height) >= 384) continue;
            $dimensions = $newW . 'x' . $newH;
            if ($dimensions === $lastDimensions) continue;
            $lastDimensions = $dimensions;
            $canvas = imagecreatetruecolor($newW,$newH);
            if (!$canvas instanceof GdImage) throw new HttpError('Sem memória para processar a imagem.',500);
            try {
                imagealphablending($canvas,false);
                imagesavealpha($canvas,true);
                $transparent = imagecolorallocatealpha($canvas,0,0,0,127);
                imagefilledrectangle($canvas,0,0,$newW,$newH,$transparent);
                if (!imagecopyresampled($canvas,$original,0,0,0,0,$newW,$newH,$width,$height)) {
                    throw new HttpError('Não foi possível redimensionar a imagem.',500);
                }
                foreach ([88,80,72,64,56,48] as $quality) {
                    if (!@imagewebp($canvas,$tmp,$quality)) continue;
                    clearstatcache(true,$tmp);
                    $size = @filesize($tmp);
                    if ($size !== false && $size > 0 && $size <= $limit) {
                        $saved = true;
                        return ['path'=>$tmp,'extension'=>'webp','mime'=>'image/webp',
                            'size'=>(int)$size,'temporary'=>true];
                    }
                }
            } finally {
                imagedestroy($canvas);
            }
        }
        throw new HttpError('Esta imagem não coube em 30 KB com qualidade visual aceitável. Escolha uma imagem menos detalhada ou de menor resolução.');
    } finally {
        imagedestroy($original);
        if (!$saved && is_file($tmp)) @unlink($tmp);
    }
}

/** Retorna o caminho privado se houver upload, para limpeza em rollback. */
function upload_attachment(string $module, string $code, int $userId): ?string {
    if (empty($_FILES['anexo']) || ($_FILES['anexo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
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
        'webp' => ['image/webp'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];
    if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
        throw new HttpError('Anexe PDF, JPG, PNG, WebP, DOC ou DOCX válido.');
    }
    $isImage = in_array($ext,['jpg','jpeg','png','webp'],true);
    $optimized = null;
    $sourcePath = (string)$f['tmp_name'];
    if ($isImage) {
        $optimized = obras_imagem_30kb($sourcePath,$ext);
        $ext = $optimized['extension'];
        $mime = $optimized['mime'];
        $size = $optimized['size'];
        $sourcePath = $optimized['path'];
        // O nome baixado corresponde ao formato real do conteúdo convertido.
        if ($optimized['temporary']) {
            $original = pathinfo($original,PATHINFO_FILENAME) . '.webp';
        }
    }
    try {
        $dir = obras_upload_write_dir();
        $filename = bin2hex(random_bytes(20)) . '.' . $ext;
        $path = $dir . '/' . $filename;
        $moved = ($optimized !== null && $optimized['temporary'])
            ? (@rename($sourcePath, $path) || @copy($sourcePath, $path))
            : move_uploaded_file((string)$f['tmp_name'], $path);
        if (!$moved) throw new HttpError('Não foi possível salvar o anexo.', 500);
        @chmod($path, 0600);
        try {
            $st = db()->prepare('INSERT INTO attachments (module, record_code, original_name, stored_name, mime, size_bytes, uploaded_by) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$module, $code, substr($original, 0, 240), $filename, $mime, $size, $userId]);
        } catch (Throwable $e) { @unlink($path); throw $e; }
        return $path;
    } finally {
        if ($optimized !== null && $optimized['temporary'] && is_file($sourcePath)) @unlink($sourcePath);
    }
}
