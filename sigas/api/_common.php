<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\PermissionService;

if (!function_exists('sigas_api_json')) {
    /** @param array<string,mixed> $payload */
    function sigas_api_json(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

if (!function_exists('sigas_api_input')) {
    /** @return array<string,mixed> */
    function sigas_api_input(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }
}

if (!function_exists('sigas_api_context')) {
    /** @return array{pdo:PDO,user:App\Models\User,authorization:AuthorizationService,audit:AuditService} */
    function sigas_api_context(): array
    {
        $pdo = Database::connection();
        $levels = new AccessLevelRepository($pdo);
        $audit = new AuditService(new AuditLogRepository($pdo));
        $auth = new AuthService(
            new UserRepository($pdo),
            new UserSessionRepository($pdo),
            $levels,
            $audit
        );
        $user = $auth->currentUser();
        if ($user === null) {
            sigas_api_json(401, ['ok' => false, 'error' => 'Não autenticado.']);
        }
        $authorization = new AuthorizationService(
            new PermissionService(new PermissionRepository($pdo)),
            $levels
        );
        return compact('pdo', 'user', 'authorization', 'audit');
    }
}

if (!function_exists('sigas_api_require_method')) {
    function sigas_api_require_method(string $method): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
            header('Allow: ' . strtoupper($method));
            sigas_api_json(405, ['ok' => false, 'error' => 'Método não permitido.']);
        }
    }
}

if (!function_exists('sigas_api_require_permission')) {
    function sigas_api_require_permission(array $context, string $permission): void
    {
        $authorization = $context['authorization'];
        $user = $context['user'];
        if (
            $authorization->isAdministrator($user)
            || $authorization->isSupport($user)
            || $authorization->can($user, $permission)
        ) {
            return;
        }
        sigas_api_json(403, ['ok' => false, 'error' => 'Acesso negado para esta operação.']);
    }
}

if (!function_exists('sigas_api_require_csrf')) {
    /** @param array<string,mixed> $input */
    function sigas_api_require_csrf(array $input, string $scope): void
    {
        $token = $input['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::validate(is_string($token) ? $token : null, $scope)) {
            sigas_api_json(419, ['ok' => false, 'error' => 'Sessão do formulário expirou. Recarregue a página.']);
        }
    }
}

if (!function_exists('sigas_api_exception')) {
    function sigas_api_exception(Throwable $exception): never
    {
        $code = (int) $exception->getCode();
        $allowed = [400, 401, 403, 404, 409, 419, 422];
        $status = in_array($code, $allowed, true) ? $code : 500;
        $message = $status === 500 ? 'Não foi possível concluir a operação.' : $exception->getMessage();
        sigas_api_json($status, ['ok' => false, 'error' => $message]);
    }
}
