<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\AuthorizationException;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GovernanceAccessMatrixRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\GovernanceAccessMatrixService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/** @param array<string,mixed> $payload */
function governance_matrix_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    governance_matrix_response(405, ['ok' => false, 'message' => 'Método não permitido.']);
}

try {
    $pdo = Database::connection();
    $users = new UserRepository($pdo);
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService($users, new UserSessionRepository($pdo), $levels, $audit);
    $operator = $auth->currentUser();

    if ($operator === null) {
        governance_matrix_response(401, ['ok' => false, 'message' => 'Sua sessão expirou. Entre novamente.']);
    }

    if (!Csrf::validateAndRotate(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null, 'governance-access-matrix')) {
        governance_matrix_response(419, [
            'ok' => false,
            'message' => 'A sessão do formulário expirou. Atualize a página e tente novamente.',
            'csrf' => Csrf::token('governance-access-matrix'),
        ]);
    }

    $levelId = filter_var($_POST['nivel_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($levelId === false) {
        throw new InvalidArgumentException('Selecione um nível de acesso válido.');
    }

    $rawPermissionIds = $_POST['permission_ids'] ?? [];
    if (!is_array($rawPermissionIds)) {
        throw new InvalidArgumentException('Lista de permissões inválida.');
    }

    $authorization = new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        $levels
    );
    $service = new GovernanceAccessMatrixService(
        new GovernanceAccessMatrixRepository($pdo),
        $levels,
        $authorization,
        $audit,
    );

    $result = $service->update(
        $operator,
        (int) $levelId,
        array_values(array_map('intval', $rawPermissionIds)),
        (string) ($_POST['motivo'] ?? ''),
    );

    governance_matrix_response(200, [
        'ok' => true,
        'message' => $result['message'],
        'revoked_sessions' => $result['revoked_sessions'],
        'affected_users' => $result['affected_users'],
        'permission_ids' => $result['permission_ids'],
        'csrf' => Csrf::token('governance-access-matrix'),
    ]);
} catch (AuthorizationException $exception) {
    governance_matrix_response(403, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-access-matrix'),
    ]);
} catch (InvalidArgumentException $exception) {
    governance_matrix_response(422, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-access-matrix'),
    ]);
} catch (Throwable $exception) {
    Logger::application('Governance access matrix action failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    governance_matrix_response(500, [
        'ok' => false,
        'message' => 'Não foi possível atualizar a matriz de acesso agora.',
        'csrf' => Csrf::token('governance-access-matrix'),
    ]);
}
