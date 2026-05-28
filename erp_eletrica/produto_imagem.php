<?php
// Serves product images from persistent hosting storage outside the app deploy folder.

$file = basename((string)($_GET['f'] ?? ''));

if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|gif|webp|avif|bmp|ico|svg)$/i', $file)) {
    http_response_code(404);
    exit;
}

$appRoot = __DIR__;
$persistentDir = getenv('ERP_PRODUCT_UPLOAD_DIR');
if (!$persistentDir) {
    $persistentDir = dirname(dirname($appRoot)) . DIRECTORY_SEPARATOR . 'erp_eletrica_uploads' . DIRECTORY_SEPARATOR . 'produtos';
}

$candidates = [
    rtrim($persistentDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file,
    $appRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'produtos' . DIRECTORY_SEPARATOR . $file,
];

$path = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $path = $candidate;
        break;
    }
}

if (!$path) {
    http_response_code(404);
    exit;
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = $finfo ? finfo_file($finfo, $path) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($detected) {
        $mime = $detected;
    }
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
readfile($path);
