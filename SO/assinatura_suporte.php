<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

function assinatura_http_error($status, $message)
{
    http_response_code((int)$status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $message;
    exit;
}

$nivel = strtoupper((string)($_SESSION['nivel'] ?? ''));
if ($nivel !== 'SUPORTE') {
    assinatura_http_error(403, 'Acesso negado.');
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, arquivo_path
        FROM assinaturas_sistema
        WHERE finalidade = :finalidade
          AND ativo = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':finalidade' => 'AUTORIZACAO_FORNECEDOR',
    ]);
    $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    assinatura_http_error(500, 'Não foi possível carregar a assinatura.');
}

if (!$assinatura || empty($assinatura['arquivo_path'])) {
    assinatura_http_error(404, 'Nenhuma assinatura ativa foi encontrada.');
}

$arquivo_relativo = trim((string)$assinatura['arquivo_path']);
$arquivo_relativo = str_replace('\\', '/', $arquivo_relativo);

if ($arquivo_relativo === '' || strpos($arquivo_relativo, "\0") !== false) {
    assinatura_http_error(404, 'Arquivo de assinatura indisponível.');
}

if (preg_match('~(^|/)\.\.(/|$)~', $arquivo_relativo)) {
    assinatura_http_error(404, 'Arquivo de assinatura indisponível.');
}

/*
 * Aceita tanto "storage/assinaturas/..." quanto "SO/storage/assinaturas/..."
 * no banco, mas nunca permite sair da pasta privada de assinaturas.
 */
if (strpos($arquivo_relativo, 'SO/') === 0) {
    $arquivo_relativo = substr($arquivo_relativo, 3);
}

$base_assinaturas = realpath(__DIR__ . '/storage/assinaturas');
$caminho_arquivo = realpath(__DIR__ . '/' . ltrim($arquivo_relativo, '/'));

if ($base_assinaturas === false || $caminho_arquivo === false || !is_file($caminho_arquivo)) {
    assinatura_http_error(404, 'Arquivo de assinatura indisponível.');
}

$prefixo_permitido = rtrim($base_assinaturas, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($caminho_arquivo, $prefixo_permitido) !== 0) {
    assinatura_http_error(403, 'Arquivo de assinatura não autorizado.');
}

$mime = '';
$inicio_arquivo = @file_get_contents($caminho_arquivo, false, null, 0, 512);
if (is_string($inicio_arquivo) && preg_match('/^\s*<svg\b/i', $inicio_arquivo)) {
    $mime = 'image/svg+xml';
} elseif (function_exists('finfo_open')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime_detectado = @finfo_file($finfo, $caminho_arquivo);
        @finfo_close($finfo);
        if (is_string($mime_detectado)) {
            $mime = strtolower(trim($mime_detectado));
        }
    }
}

$extensao = strtolower(pathinfo($caminho_arquivo, PATHINFO_EXTENSION));
if ($mime === '') {
    $mime_por_extensao = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];
    $mime = $mime_por_extensao[$extensao] ?? '';
}

$mimes_permitidos = [
    'image/png',
    'image/jpeg',
    'image/gif',
    'image/webp',
    'image/svg+xml',
];

if (!in_array($mime, $mimes_permitidos, true)) {
    assinatura_http_error(415, 'Formato de assinatura não suportado.');
}

$extensao_saida = 'png';
if ($mime === 'image/jpeg') {
    $extensao_saida = 'jpg';
} elseif ($mime === 'image/gif') {
    $extensao_saida = 'gif';
} elseif ($mime === 'image/webp') {
    $extensao_saida = 'webp';
} elseif ($mime === 'image/svg+xml') {
    $extensao_saida = 'svg';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="assinatura.' . $extensao_saida . '"');
header('Content-Length: ' . (string)filesize($caminho_arquivo));
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; style-src \'none\'; sandbox');

readfile($caminho_arquivo);
exit;
