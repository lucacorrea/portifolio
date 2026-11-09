<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\AuthorizationException;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GovernanceAccessLevelRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\GovernanceAccessLevelService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/** @param array<string,mixed> $payload */
function governance_level_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    governance_level_response(405, ['ok' => false, 'message' => 'Método não permitido.']);
}

try {
    $pdo = Database::connection();
    $users = new UserRepository($pdo);
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService($users, new UserSessionRepository($pdo), $levels, $audit);
    $operator = $auth->currentUser();

    if ($operator === null) {
        governance_level_response(401, ['ok' => false, 'message' => 'Sua sessão expirou. Entre novamente.']);
    }

    if (!Csrf::validateAndRotate(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null, 'governance-access-level')) {
        governance_level_response(419, [
            'ok' => false,
            'message' => 'A sessão do formulário expirou. Atualize a página e tente novamente.',
            'csrf' => Csrf::token('governance-access-level'),
        ]);
    }

    $authorization = new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        $levels,
    );
    $service = new GovernanceAccessLevelService(
        new GovernanceAccessLevelRepository($pdo),
        $authorization,
        $audit,
    );

    $action = trim((string) ($_POST['acao'] ?? ''));
    $reason = (string) ($_POST['motivo'] ?? '');
    $levelId = filter_var($_POST['nivel_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($action === 'create') {
        $priority = filter_var($_POST['prioridade'] ?? null, FILTER_VALIDATE_INT);
        if ($priority === false) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }
        $result = $service->create(
            $operator,
            (string) ($_POST['nome'] ?? ''),
            isset($_POST['descricao']) ? (string) $_POST['descricao'] : null,
            (int) $priority,
            $reason,
        );
    } elseif ($action === 'update') {
        if ($levelId === false) {
            throw new InvalidArgumentException('Nível de acesso inválido.');
        }
        $priority = filter_var($_POST['prioridade'] ?? null, FILTER_VALIDATE_INT);
        if ($priority === false) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }
        $result = $service->update(
            $operator,
            (int) $levelId,
            (string) ($_POST['nome'] ?? ''),
            isset($_POST['descricao']) ? (string) $_POST['descricao'] : null,
            (int) $priority,
            $reason,
        );
    } elseif ($action === 'activate' || $action === 'deactivate') {
        if ($levelId === false) {
            throw new InvalidArgumentException('Nível de acesso inválido.');
        }
        $result = $service->setActive($operator, (int) $levelId, $action === 'activate', $reason);
    } else {
        throw new InvalidArgumentException('Ação de nível inválida.');
    }

    governance_level_response(200, [
        'ok' => true,
        ...$result,
        'csrf' => Csrf::token('governance-access-level'),
    ]);
} catch (AuthorizationException $exception) {
    governance_level_response(403, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-access-level'),
    ]);
} catch (InvalidArgumentException $exception) {
    governance_level_response(422, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-access-level'),
    ]);
} catch (Throwable $exception) {
    Logger::application('Governance access level action failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    governance_level_response(500, [
        'ok' => false,
        'message' => 'Não foi possível concluir a gestão do nível agora.',
        'csrf' => Csrf::token('governance-access-level'),
    ]);
}
