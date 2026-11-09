<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\AuthorizationException;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GovernancePermissionRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\GovernancePermissionService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/** @param array<string,mixed> $payload */
function governance_permission_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    governance_permission_response(405, ['ok' => false, 'message' => 'Método não permitido.']);
}

try {
    $pdo = Database::connection();
    $users = new UserRepository($pdo);
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService($users, new UserSessionRepository($pdo), $levels, $audit);
    $operator = $auth->currentUser();

    if ($operator === null) {
        governance_permission_response(401, ['ok' => false, 'message' => 'Sua sessão expirou. Entre novamente.']);
    }

    if (!Csrf::validateAndRotate(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null, 'governance-permission')) {
        governance_permission_response(419, [
            'ok' => false,
            'message' => 'A sessão do formulário expirou. Atualize a página e tente novamente.',
            'csrf' => Csrf::token('governance-permission'),
        ]);
    }

    $authorization = new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        $levels
    );
    $service = new GovernancePermissionService(
        new GovernancePermissionRepository($pdo),
        $authorization,
        $audit,
    );

    $action = trim((string) ($_POST['acao'] ?? ''));

    if ($action === 'create') {
        $result = $service->create(
            $operator,
            (string) ($_POST['nome'] ?? ''),
            (string) ($_POST['modulo'] ?? ''),
            (string) ($_POST['acao_chave'] ?? ''),
            isset($_POST['descricao']) ? (string) $_POST['descricao'] : null,
            (string) ($_POST['motivo'] ?? ''),
        );
    } elseif ($action === 'update') {
        $permissionId = filter_var($_POST['permissao_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($permissionId === false) {
            throw new InvalidArgumentException('Permissão inválida.');
        }
        $result = $service->update(
            $operator,
            (int) $permissionId,
            (string) ($_POST['nome'] ?? ''),
            isset($_POST['descricao']) ? (string) $_POST['descricao'] : null,
            (string) ($_POST['motivo'] ?? ''),
        );
    } elseif ($action === 'activate' || $action === 'deactivate') {
        $permissionId = filter_var($_POST['permissao_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($permissionId === false) {
            throw new InvalidArgumentException('Permissão inválida.');
        }
        $result = $service->setActive(
            $operator,
            (int) $permissionId,
            $action === 'activate',
            (string) ($_POST['motivo'] ?? ''),
        );
    } else {
        throw new InvalidArgumentException('Ação de permissão inválida.');
    }

    governance_permission_response(200, [
        'ok' => true,
        ...$result,
        'csrf' => Csrf::token('governance-permission'),
    ]);
} catch (AuthorizationException $exception) {
    governance_permission_response(403, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-permission'),
    ]);
} catch (InvalidArgumentException|RuntimeException $exception) {
    governance_permission_response(422, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-permission'),
    ]);
} catch (Throwable $exception) {
    Logger::application('Governance permission action failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    governance_permission_response(500, [
        'ok' => false,
        'message' => 'Não foi possível concluir a ação de permissão agora.',
        'csrf' => Csrf::token('governance-permission'),
    ]);
}
