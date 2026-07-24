<?php

declare(strict_types=1);

use App\Core\Application;
use App\Security\SessionManager;

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

/** @return array{0:Application,1:SessionManager} */
function client_action_context(string $permission): array
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
        error_log('Client action access failed: ' . $exception->getMessage());
        $session->flash('danger', 'Não foi possível validar a operação.');
        client_redirect($application);
    }

    return [$application, $session];
}

function client_redirect(Application $application, string $target = 'clientes.php'): never
{
    header('Location: ' . $application->redirect()->applicationUrl(client_return_target($application, $target)), true, 303);
    exit;
}

function client_return_target(Application $application, string $default): string
{
    return action_return_target($application, $default);
}

function client_require_post_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
}

function client_posted_positive_int(string $key): int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($value)) throw new InvalidArgumentException('Identificador inválido.');
    return $value;
}

function client_store_form_recovery(string $modal, array $data, string $error): void
{
    unset($data['csrf_token'], $data['code'], $data['codigo']);
    $_SESSION['client_form_recovery'] = [
        'modal' => $modal,
        'error' => $error,
        'data' => array_filter($data, static fn(mixed $value): bool => is_scalar($value) || $value === null),
    ];
}

/** @return array{modal:string,error:string,data:array<string,mixed>}|null */
function client_consume_form_recovery(): ?array
{
    if (!isset($_SESSION['client_form_recovery']) || !is_array($_SESSION['client_form_recovery'])) {
        unset($_SESSION['client_form_recovery']);
        return null;
    }
    $recovery = $_SESSION['client_form_recovery'];
    unset($_SESSION['client_form_recovery']);
    if (!isset($recovery['modal'], $recovery['error'], $recovery['data']) || !is_string($recovery['modal']) || !is_string($recovery['error']) || !is_array($recovery['data'])) {
        return null;
    }
    return ['modal' => $recovery['modal'], 'error' => $recovery['error'], 'data' => $recovery['data']];
}

function client_store_import_preview(array $preview): void
{
    unset($preview['rows']);
    $_SESSION['client_import_preview'] = $preview;
}

/** @return array<string,mixed>|null */
function client_import_preview(): ?array
{
    $preview = $_SESSION['client_import_preview'] ?? null;
    if (!is_array($preview)
        || preg_match('/^[a-f0-9]{48}$/', (string) ($preview['token'] ?? '')) !== 1
        || !is_array($preview['summary'] ?? null)
        || !is_array($preview['preview'] ?? null)
    ) {
        unset($_SESSION['client_import_preview']);
        return null;
    }
    return $preview;
}

function client_clear_import_preview(): void
{
    unset($_SESSION['client_import_preview']);
}
