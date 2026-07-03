<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Validator;
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

function respond_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond_json(405, ['ok' => false, 'error' => 'Método não permitido.']);
}

try {
    $pdo = Database::connection();
    $userRepository = new UserRepository($pdo);
    $sessionRepository = new UserSessionRepository($pdo);
    $accessLevelRepository = new AccessLevelRepository($pdo);
    $authService = new AuthService(
        $userRepository,
        $sessionRepository,
        $accessLevelRepository,
        new AuditService(new AuditLogRepository($pdo))
    );
    $user = $authService->currentUser();

    if ($user === null) {
        respond_json(401, ['ok' => false, 'error' => 'Não autenticado.']);
    }

    $authorization = new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        $accessLevelRepository
    );

    if (!$authorization->can($user, 'comida_mesa.consultar_cpf')) {
        respond_json(403, ['ok' => false, 'error' => 'Acesso negado.']);
    }

    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

    if (!Csrf::validate($token, 'comida_mesa_consultar_cpf')) {
        respond_json(419, ['ok' => false, 'error' => 'Requisição inválida.']);
    }

    $cpf = isset($_POST['cpf']) && is_string($_POST['cpf']) ? Validator::onlyDigits($_POST['cpf']) : '';
    $competenceId = isset($_POST['competencia_id']) && is_string($_POST['competencia_id']) && preg_match('/^\d+$/', $_POST['competencia_id']) === 1
        ? (int) $_POST['competencia_id']
        : null;

    if ($cpf === '') {
        respond_json(400, ['ok' => false, 'error' => 'CPF não informado.']);
    }

    if (!Validator::cpf($cpf)) {
        respond_json(422, ['ok' => false, 'error' => 'CPF inválido.']);
    }

    $service = new ComidaMesaService(new ComidaMesaRepository($pdo));
    respond_json(200, $service->consultCpf($cpf, $competenceId));
} catch (Throwable $exception) {
    Logger::application('Comida Mesa CPF consultation failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);
    respond_json(500, ['ok' => false, 'error' => 'Não foi possível concluir a consulta.']);
}
