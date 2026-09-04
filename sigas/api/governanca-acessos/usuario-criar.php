<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\AuthorizationException;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\GovernanceUserRegistrationService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/** @param array<string,mixed> $payload */
function governance_user_create_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    governance_user_create_response(405, ['ok' => false, 'message' => 'Método não permitido.']);
}

try {
    $pdo = Database::connection();
    $users = new UserRepository($pdo);
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService($users, new UserSessionRepository($pdo), $levels, $audit);
    $operator = $auth->currentUser();

    if ($operator === null) {
        governance_user_create_response(401, ['ok' => false, 'message' => 'Sua sessão expirou. Entre novamente.']);
    }

    if (!Csrf::validateAndRotate(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null, 'governance-user-create')) {
        governance_user_create_response(419, [
            'ok' => false,
            'message' => 'A sessão do formulário expirou. Atualize a página e tente novamente.',
            'csrf' => Csrf::token('governance-user-create'),
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

    $requestedSectorId = filter_var(
        $_POST['setor_solicitado_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($requestedSectorId === false) {
        throw new InvalidArgumentException('Selecione o setor solicitado.');
    }

    $service = new GovernanceUserRegistrationService(
        $users,
        new SectorRepository($pdo),
        $audit,
    );

    $userId = $service->createPending(
        $operator,
        (string) ($_POST['nome'] ?? ''),
        (string) ($_POST['cpf'] ?? ''),
        isset($_POST['matricula']) ? (string) $_POST['matricula'] : null,
        isset($_POST['cargo']) ? (string) $_POST['cargo'] : null,
        (string) ($_POST['email'] ?? ''),
        isset($_POST['telefone']) ? (string) $_POST['telefone'] : null,
        (int) $requestedSectorId,
        (string) ($_POST['senha'] ?? ''),
        (string) ($_POST['senha_confirmacao'] ?? ''),
    );

    governance_user_create_response(201, [
        'ok' => true,
        'message' => 'Usuário criado como pendente. Agora defina o nível e aprove o acesso.',
        'user_id' => $userId,
        'csrf' => Csrf::token('governance-user-create'),
    ]);
} catch (AuthorizationException $exception) {
    governance_user_create_response(403, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-user-create'),
    ]);
} catch (InvalidArgumentException $exception) {
    governance_user_create_response(422, [
        'ok' => false,
        'message' => $exception->getMessage(),
        'csrf' => Csrf::token('governance-user-create'),
    ]);
} catch (Throwable $exception) {
    Logger::application('Governance user creation failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    governance_user_create_response(500, [
        'ok' => false,
        'message' => 'Não foi possível criar o usuário agora.',
        'csrf' => Csrf::token('governance-user-create'),
    ]);
}
