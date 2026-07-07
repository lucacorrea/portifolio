<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\DTO\ComidaMesaCadastroData;
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

function cm_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cm_error(RuntimeException $exception): never
{
    $payload = ['ok' => false, 'error' => 'Não foi possível concluir a operação.'];

    if ($exception->getCode() === 422) {
        $decoded = json_decode($exception->getMessage(), true);
        $payload['error'] = 'Revise os campos informados.';
        if (is_array($decoded['fields'] ?? null)) {
            $payload['fields'] = $decoded['fields'];
        }
    } elseif (in_array($exception->getCode(), [400, 403, 404, 409, 419], true)) {
        $payload['error'] = $exception->getMessage();
    }

    $status = in_array($exception->getCode(), [400, 403, 404, 409, 419, 422], true)
        ? $exception->getCode()
        : 500;
    cm_json($status, $payload);
}

function cm_context(PDO $pdo): array
{
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService(new UserRepository($pdo), new UserSessionRepository($pdo), $levels, $audit);
    $user = $auth->currentUser();

    if ($user === null) {
        cm_json(401, ['ok' => false, 'error' => 'Não autenticado.']);
    }

    return [
        $user,
        new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $levels),
        $audit,
    ];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        cm_json(405, ['ok' => false, 'error' => 'Método não permitido.']);
    }

    if (!Csrf::validate($_POST['_csrf'] ?? null, 'comida_mesa_salvar_cadastro')) {
        cm_json(419, ['ok' => false, 'error' => 'Requisição inválida.']);
    }

    $pdo = Database::connection();
    [$user, $authorization, $audit] = cm_context($pdo);
    $data = ComidaMesaCadastroData::fromArray($_POST);
    $permission = $data->registrationId === null ? 'comida_mesa.cadastrar' : 'comida_mesa.editar';

    if (!$authorization->can($user, $permission)) {
        cm_json(403, ['ok' => false, 'error' => 'Acesso negado.']);
    }

    $result = (new ComidaMesaService(new ComidaMesaRepository($pdo)))->saveRegistration($data, $user->id, $audit);

    cm_json($result['created'] ? 201 : 200, [
        'ok' => true,
        'message' => $result['created'] ? 'Cadastro criado.' : 'Cadastro atualizado.',
        'data' => $result,
    ]);
} catch (RuntimeException $exception) {
    cm_error($exception);
} catch (Throwable $exception) {
    Logger::application('Comida Mesa save registration failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);
    cm_json(500, ['ok' => false, 'error' => 'Não foi possível salvar o cadastro.']);
}
