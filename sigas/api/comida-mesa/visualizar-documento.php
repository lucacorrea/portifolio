<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ComidaMesaRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ComidaMesaDocumentService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Cache-Control: private, no-store');

function document_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        header('Allow: GET');
        document_error(405, 'Método não permitido.');
    }

    $pdo = Database::connection();
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService(new UserRepository($pdo), new UserSessionRepository($pdo), $levels, $audit);
    $user = $auth->currentUser();

    if ($user === null) {
        document_error(401, 'Não autenticado.');
    }

    $authorization = new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $levels);

    if (!$authorization->can($user, 'comida_mesa.documentos_visualizar')) {
        document_error(403, 'Acesso negado.');
    }

    $id = isset($_GET['id']) && preg_match('/^\d+$/', (string) $_GET['id']) === 1 ? (int) $_GET['id'] : 0;
    $document = (new ComidaMesaDocumentService(new ComidaMesaRepository($pdo)))->resolveForView($id);
    $path = (string) $document['absolute_path'];
    $filename = basename((string) $document['nome_original']);
    $filename = preg_replace('/[\x00-\x1F\x7F"\\\\]/u', '_', $filename) ?: 'documento';

    header('Content-Type: ' . (string) $document['mime_type']);
    header('Content-Length: ' . (string) filesize($path));
    header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: inline; filename=\"documento\"; filename*=UTF-8''" . rawurlencode($filename));
    readfile($path);
    exit;
} catch (RuntimeException $exception) {
    document_error($exception->getCode() === 403 ? 403 : 404, 'Documento não encontrado.');
} catch (Throwable $exception) {
    Logger::application('Comida Mesa document view failed.', ['type' => $exception::class, 'code' => $exception->getCode()]);
    document_error(404, 'Documento não encontrado.');
}
