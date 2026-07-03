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
use App\Services\ComidaMesaService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cm_json(int $status, array $payload): never { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function cm_auth(PDO $pdo): array {
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService(new UserRepository($pdo), new UserSessionRepository($pdo), $levels, $audit);
    $user = $auth->currentUser();
    if ($user === null) cm_json(401, ['ok' => false, 'error' => 'Não autenticado.']);
    $authorization = new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $levels);
    return [$user, $authorization, $audit];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); cm_json(405, ['ok' => false, 'error' => 'Método não permitido.']); }
    $pdo = Database::connection();
    [$user, $authorization] = cm_auth($pdo);
    if (!$authorization->can($user, 'comida_mesa.visualizar')) cm_json(403, ['ok' => false, 'error' => 'Acesso negado.']);
    $id = isset($_GET['id']) && preg_match('/^\d+$/', (string) $_GET['id']) === 1 ? (int) $_GET['id'] : 0;
    if ($id < 1) cm_json(400, ['ok' => false, 'error' => 'Inscrição não informada.']);
    $service = new ComidaMesaService(new ComidaMesaRepository($pdo));
    $data = $service->detail($id, $authorization->can($user, 'comida_mesa.editar'), $authorization->can($user, 'comida_mesa.documentos_visualizar'), $authorization->can($user, 'comida_mesa.historico_visualizar'));
    cm_json(200, ['ok' => true, 'data' => $data]);
} catch (RuntimeException $exception) {
    cm_json(in_array($exception->getCode(), [400, 403, 404, 409, 422], true) ? $exception->getCode() : 500, ['ok' => false, 'error' => $exception->getCode() === 404 ? 'Registro inexistente.' : $exception->getMessage()]);
} catch (Throwable $exception) {
    Logger::application('Comida Mesa detail failed.', ['type' => $exception::class, 'code' => $exception->getCode()]);
    cm_json(500, ['ok' => false, 'error' => 'Não foi possível carregar os detalhes.']);
}
