<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;

require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Método não permitido.';
    exit;
}

$token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

if (!Csrf::validateAndConsume($token, 'logout')) {
    http_response_code(419);
    echo 'Requisição inválida.';
    exit;
}

$pdo = Database::connection();
$authService = new AuthService(
    new UserRepository($pdo),
    new UserSessionRepository($pdo),
    new AccessLevelRepository($pdo),
    new AuditService(new AuditLogRepository($pdo))
);

$authService->logout();

header('Location: index.php');
exit;
