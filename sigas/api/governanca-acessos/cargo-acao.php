<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\AuthorizationException;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CargoRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\GovernanceCargoService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/** @param array<string,mixed> $payload */
function governance_cargo_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    governance_cargo_response(405, ['ok' => false, 'message' => 'Método não permitido.']);
}

try {
    $pdo = Database::connection();
    $users = new UserRepository($pdo);
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService($users, new UserSessionRepository($pdo), $levels, $audit);
    $operator = $auth->currentUser();

    if ($operator === null) {
        governance_cargo_response(401, ['ok' => false, 'message' => 'Sua sessão expirou. Entre novamente.']);
    }

    if (!Csrf::validateAndRotate(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null, 'governance-cargo')) {
        governance_cargo_response(419, [
            'ok' => false,
            'message' => 'A sessão do formulário expirou. Atualize a página e tente novamente.',
            'csrf' => Csrf::token('governance-cargo'),
        ]);
    }

    $authorization = new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        $levels
    );
    if (!$authorization->isAdministrator($operator) && !$authorization->isSupport($operator)) {
        throw new AuthorizationException('Acesso administrativo restrito à Governança e Acessos.');
    }
    $authorization->requirePermission($operator, 'usuarios.editar');

    $service = new GovernanceCargoService(new CargoRepository($pdo), $audit);
    $action = trim((string) ($_POST['acao'] ?? ''));
    $message = '';

    if ($action === 'create') {
        $service->create(
            $operator,
            (string) ($_POST['nome'] ?? ''),
            isset($_POST['descricao']) ? (string) $_POST['descricao'] : null,
        );
        $message = 'Cargo cadastrado com sucesso.';
    } elseif ($action === 'update') {
        $id = filter_var($_POST['cargo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Cargo inválido.');
        }
        $service->update(
            $operator,
            (int) $id,
            (string) ($_POST['nome'] ?? ''),
            isset($_POST['descricao']) ? (string) $_POST['descricao'] : null,
        );
        $message = 'Cargo atualizado com sucesso.';
    } elseif ($action === 'activate' || $action === 'deactivate') {
        $id = filter_var($_POST['cargo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Cargo inválido.');
        }
        $service->setActive($operator, (int) $id, $action === 'activate');
        $message = $action === 'activate'
            ? 'Cargo ativado e disponível para novas atribuições.'
            : 'Cargo inativado para novas atribuições.';
    } else {
        throw new InvalidArgumentException('Ação de cargo inválida.');
    }

    governance_cargo_response(200, [
        'ok' => true,
        'message' => $message,
        'csrf' => Csrf::token('governance-cargo'),
    ]);
} catch (AuthorizationException $exception) {
    governance_cargo_response(403, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-cargo'),
    ]);
} catch (InvalidArgumentException|RuntimeException $exception) {
    governance_cargo_response(422, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-cargo'),
    ]);
} catch (Throwable $exception) {
    Logger::application('Governance cargo action failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    governance_cargo_response(500, [
        'ok' => false,
        'message' => 'Não foi possível concluir a ação de cargo agora.',
        'csrf' => Csrf::token('governance-cargo'),
    ]);
}
