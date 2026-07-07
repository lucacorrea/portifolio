<?php

declare(strict_types=1);

use App\Core\Csrf;
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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cm_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

function cm_error(RuntimeException $exception): never
{
    $payload = ['ok' => false, 'error' => $exception->getMessage()];

    if ($exception->getCode() === 422) {
        $decoded = json_decode($exception->getMessage(), true);
        $payload = ['ok' => false, 'error' => 'Revise os campos informados.'];
        if (is_array($decoded['fields'] ?? null)) {
            $payload['fields'] = $decoded['fields'];
        }
    }

    $status = in_array($exception->getCode(), [400, 403, 404, 409, 419, 422], true)
        ? $exception->getCode()
        : 500;
    cm_json($status, $payload);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        cm_json(405, ['ok' => false, 'error' => 'Método não permitido.']);
    }

    if (!Csrf::validate($_POST['_csrf'] ?? null, 'comida_mesa_enviar_documento')) {
        cm_json(419, ['ok' => false, 'error' => 'Requisição inválida.']);
    }

    $pdo = Database::connection();
    [$user, $authorization, $audit] = cm_context($pdo);

    if (!$authorization->can($user, 'comida_mesa.documentos_enviar')) {
        cm_json(403, ['ok' => false, 'error' => 'Acesso negado.']);
    }

    $registrationId = isset($_POST['inscricao_id'])
        && preg_match('/^\d+$/', (string) $_POST['inscricao_id']) === 1
            ? (int) $_POST['inscricao_id']
            : 0;
    $file = is_array($_FILES['arquivo'] ?? null) ? $_FILES['arquivo'] : [];

    $result = (new ComidaMesaDocumentService(new ComidaMesaRepository($pdo)))->store(
        $registrationId,
        (string) ($_POST['tipo'] ?? ''),
        isset($_POST['descricao']) ? (string) $_POST['descricao'] : null,
        $file,
        $user->id,
        $user->setorId,
        $audit
    );

    cm_json(201, ['ok' => true, 'message' => 'Documento enviado.', 'data' => $result]);
} catch (RuntimeException $exception) {
    cm_error($exception);
} catch (Throwable $exception) {
    Logger::application('Comida Mesa document upload failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);
    cm_json(500, ['ok' => false, 'error' => 'Não foi possível enviar o documento.']);
}
