<?php

declare(strict_types=1);

use App\Company\Service\CompanyLogoStorage;
use App\Core\Application;

try {
    $app = require __DIR__ . '/bootstrap.php';
    /** @var Application $application */
    $application = $app['application'];
    $session = $application->session();
    $session->start();
    $application->authorization()->requireLogin();
    session_write_close();

    $version = isset($_GET['v']) && is_string($_GET['v']) ? trim($_GET['v']) : '';
    $storage = new CompanyLogoStorage(__DIR__ . '/storage');
    $path = $storage->resolve('empresa-logo.php?v=' . $version);
    if ($path === null) {
        throw new RuntimeException('Logo não encontrada.');
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($extension) {
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => throw new RuntimeException('Tipo de logo inválido.'),
    };
    $modifiedAt = filemtime($path);
    $size = filesize($path);
    if ($modifiedAt === false || $size === false) {
        throw new RuntimeException('Logo indisponível.');
    }

    $etag = '"' . hash('sha256', $path . '|' . $modifiedAt . '|' . $size) . '"';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) $size);
    header('Content-Disposition: inline; filename="logo-empresa.' . $extension . '"');
    header('Cache-Control: private, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    header('ETag: ' . $etag);
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }

    readfile($path);
} catch (Throwable) {
    http_response_code(404);
}
