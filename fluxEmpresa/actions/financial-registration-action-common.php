<?php

declare(strict_types=1);

use App\Core\Application;
use App\Security\SessionManager;

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

/** @return array{0:Application,1:SessionManager} */
function financial_registration_context(string $permission, string $defaultPage): array
{
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Application $application */
    $application = $app['application'];
    $session = $application->session();
    $session->start();

    try {
        $application->csrf()->requireValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
        $authorization = $application->authorization();
        $authorization->requireLogin();
        $authorization->requirePermission($permission);
    } catch (Throwable $exception) {
        error_log('Financial registration access failed: ' . $exception->getMessage());
        $session->flash('danger', 'Não foi possível validar a operação.');
        financial_registration_redirect($application, $defaultPage);
    }

    return [$application, $session];
}

function financial_registration_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
}

function financial_registration_positive_int(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($id)) throw new InvalidArgumentException('Identificador inválido.');
    return $id;
}

function financial_registration_store_recovery(string $key, string $mode, array $data, string $error): void
{
    unset($data['csrf_token'], $data['return_to']);
    $_SESSION[$key] = [
        'mode' => $mode,
        'error' => $error,
        'data' => array_filter($data, static fn(mixed $value): bool => is_scalar($value) || $value === null),
    ];
}

/** @return array{mode:string,error:string,data:array<string,mixed>}|null */
function financial_registration_consume_recovery(string $key): ?array
{
    $recovery = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    if (!is_array($recovery)
        || !is_string($recovery['mode'] ?? null)
        || !is_string($recovery['error'] ?? null)
        || !is_array($recovery['data'] ?? null)
    ) {
        return null;
    }
    return ['mode' => $recovery['mode'], 'error' => $recovery['error'], 'data' => $recovery['data']];
}

function financial_registration_redirect(Application $application, string $defaultPage): never
{
    header('Location: ' . $application->redirect()->applicationUrl(action_return_target($application, $defaultPage)), true, 303);
    exit;
}
