<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

use App\Security\Auth;
use App\Services\PwaManifestService;

Auth::requireLogin();

$empresaId = (int)(Auth::user()['empresa_id'] ?? 0);
$requestedCompanyId = filter_input(INPUT_GET, 'empresa_id', FILTER_VALIDATE_INT);

if ($requestedCompanyId !== null && $requestedCompanyId !== false && (int)$requestedCompanyId !== $empresaId) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Empresa inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    echo json_encode(
        (new PwaManifestService())->manifestForCompany($empresaId),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
} catch (Throwable $e) {
    log_app_exception($e);
    echo json_encode([
        'name' => 'Sistema de Gestão',
        'short_name' => 'Gestão',
        'start_url' => './index.php?source=pwa',
        'scope' => './',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#F1F5FC',
        'theme_color' => '#1657A7',
        'icons' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
