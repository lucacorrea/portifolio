<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\AuthorizationException;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GovernanceUserAdminRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\GovernanceUserAdministrationService;
use App\Services\PermissionService;
use App\Services\UserAdministrationPolicy;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/** @param array<string,mixed> $payload */
function governance_user_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

/** @return int|null */
function governance_optional_positive_int(mixed $value, string $field): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($parsed === false) {
        throw new InvalidArgumentException($field . ' inválido.');
    }

    return (int) $parsed;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    governance_user_response(405, ['ok' => false, 'message' => 'Método não permitido.']);
}

try {
    $pdo = Database::connection();
    $users = new UserRepository($pdo);
    $sessions = new UserSessionRepository($pdo);
    $levels = new AccessLevelRepository($pdo);
    $sectors = new SectorRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService($users, $sessions, $levels, $audit);
    $operator = $auth->currentUser();

    if ($operator === null) {
        governance_user_response(401, ['ok' => false, 'message' => 'Sua sessão expirou. Entre novamente.']);
    }

    if (!Csrf::validateAndRotate(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null, 'governance-user-admin')) {
        governance_user_response(419, ['ok' => false, 'message' => 'A sessão do formulário expirou. Atualize a página e tente novamente.']);
    }

    $targetUserId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($targetUserId === false) {
        throw new InvalidArgumentException('Usuário inválido.');
    }

    $authorization = new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        $levels
    );
    if (!$authorization->isAdministrator($operator) && !$authorization->isSupport($operator)) {
        throw new AuthorizationException('Acesso administrativo restrito à Governança e Acessos.');
    }

    $policy = new UserAdministrationPolicy($authorization, $users);
    $service = new GovernanceUserAdministrationService(
        $users,
        $sessions,
        $sectors,
        $levels,
        $policy,
        $authorization,
        $audit,
        new GovernanceUserAdminRepository($pdo)
    );

    $result = $service->execute(
        $operator,
        trim((string) ($_POST['acao'] ?? '')),
        (int) $targetUserId,
        governance_optional_positive_int($_POST['setor_id'] ?? null, 'Setor'),
        governance_optional_positive_int($_POST['nivel_id'] ?? null, 'Nível'),
        (string) ($_POST['motivo'] ?? '')
    );

    governance_user_response(200, [
        'ok' => true,
        'message' => $result['message'],
        'revoked_sessions' => $result['revoked_sessions'],
        'csrf' => Csrf::token('governance-user-admin'),
    ]);
} catch (AuthorizationException $exception) {
    governance_user_response(403, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-user-admin'),
    ]);
} catch (InvalidArgumentException $exception) {
    governance_user_response(422, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-user-admin'),
    ]);
} catch (Throwable $exception) {
    Logger::application('Governance user action failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    governance_user_response(500, [
        'ok' => false,
        'message' => 'Não foi possível concluir a ação administrativa agora.',
        'csrf' => Csrf::token('governance-user-admin'),
    ]);
}
