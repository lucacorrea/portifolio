<?php

declare(strict_types=1);

use App\Core\Application;
use App\Security\SessionManager;

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

/** @return array{0:Application,1:SessionManager} */
function budget_action_context(string $permission, bool $requireCsrf = true): array
{
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Application $application */
    $application = $app['application'];
    $session = $application->session();
    $session->start();

    try {
        if ($requireCsrf) {
            $application->csrf()->requireValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
        }
        $authorization = $application->authorization();
        $authorization->requireLogin();
        $authorization->requirePermission($permission);
    } catch (Throwable $exception) {
        error_log('Budget action access failed: ' . $exception->getMessage());
        if ($requireCsrf) {
            $session->flash('danger', 'Não foi possível validar a operação.');
            budget_redirect($application);
        }
        http_response_code(403);
        exit;
    }

    return [$application, $session];
}

function budget_redirect(Application $application, string $target = 'orcamentos.php'): never
{
    header('Location: ' . $application->redirect()->applicationUrl(budget_return_target($application, $target)), true, 303);
    exit;
}

function budget_return_target(Application $application, string $default): string
{
    return action_return_target($application, $default);
}

function budget_require_post_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
}

function budget_positive_int(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($id)) throw new InvalidArgumentException('Identificador inválido.');
    return $id;
}

function budget_posted_positive_int(string $key): int
{
    return budget_positive_int($_POST[$key] ?? null);
}

function budget_items_from_post(): array
{
    $items = [];
    $groups = ['servico' => $_POST['services'] ?? [], 'produto' => $_POST['products'] ?? [], 'outro' => $_POST['others'] ?? []];
    foreach ($groups as $type => $rows) {
        if (!is_array($rows)) continue;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $row['type'] = $type;
            unset($row['subtotal'], $row['total']);
            $items[] = $row;
        }
    }
    return $items;
}

function budget_store_form_recovery(string $modal, array $data, string $error): void
{
    unset($data['csrf_token'], $data['number'], $data['numero'], $data['total'], $data['subtotal']);
    $_SESSION['budget_form_recovery'] = ['modal' => $modal, 'error' => $error, 'data' => $data];
}

/** @return array{modal:string,error:string,data:array<string,mixed>}|null */
function budget_consume_form_recovery(): ?array
{
    if (!isset($_SESSION['budget_form_recovery']) || !is_array($_SESSION['budget_form_recovery'])) {
        unset($_SESSION['budget_form_recovery']);
        return null;
    }
    $recovery = $_SESSION['budget_form_recovery'];
    unset($_SESSION['budget_form_recovery']);
    if (!isset($recovery['modal'], $recovery['error'], $recovery['data']) || !is_string($recovery['modal']) || !is_string($recovery['error']) || !is_array($recovery['data'])) {
        return null;
    }
    return ['modal' => $recovery['modal'], 'error' => $recovery['error'], 'data' => $recovery['data']];
}
