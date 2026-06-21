<?php

declare(strict_types=1);

use App\Core\Application;
use App\Security\SessionManager;

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

/** @return array{0:Application,1:SessionManager} */
function os_action_context(string $permission, bool $requireCsrf = true): array
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
        error_log('OS action access failed: ' . $exception->getMessage());
        if ($requireCsrf) {
            $session->flash('danger', 'Não foi possível validar a operação.');
            os_redirect($application);
        }
        http_response_code(403);
        exit;
    }

    return [$application, $session];
}

function os_redirect(Application $application, string $target = 'ordens-servico.php'): never
{
    header('Location: ' . $application->redirect()->applicationUrl($target), true, 303);
    exit;
}

function os_require_post_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
}

function os_positive_int(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($id)) throw new InvalidArgumentException('Identificador inválido.');
    return $id;
}

function os_posted_positive_int(string $key): int
{
    return os_positive_int($_POST[$key] ?? null);
}

function os_items_from_post(): array
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

function os_optional_team_from_post(): ?App\ServiceOrder\DTO\ServiceOrderTeamData
{
    $primary = trim((string) ($_POST['funcionario_principal_id'] ?? ''));
    $support = trim((string) ($_POST['funcionario_apoio_id'] ?? ''));
    if ($primary === '' && $support === '') return null;
    return App\ServiceOrder\DTO\ServiceOrderTeamData::fromArray($_POST);
}

function os_optional_schedule_from_post(): ?App\ServiceOrder\DTO\ServiceOrderScheduleData
{
    return App\ServiceOrder\DTO\ServiceOrderScheduleData::fromArray($_POST);
}

function os_store_form_recovery(string $modal, array $data, string $error): void
{
    unset($data['csrf_token'], $data['total'], $data['subtotal']);
    $_SESSION['os_form_recovery'] = ['modal' => $modal, 'error' => $error, 'data' => $data];
}

/** @return array{modal:string,error:string,data:array<string,mixed>}|null */
function os_consume_form_recovery(): ?array
{
    if (!isset($_SESSION['os_form_recovery']) || !is_array($_SESSION['os_form_recovery'])) {
        unset($_SESSION['os_form_recovery']);
        return null;
    }
    $recovery = $_SESSION['os_form_recovery'];
    unset($_SESSION['os_form_recovery']);
    if (!isset($recovery['modal'], $recovery['error'], $recovery['data']) || !is_string($recovery['modal']) || !is_string($recovery['error']) || !is_array($recovery['data'])) {
        return null;
    }
    return ['modal' => $recovery['modal'], 'error' => $recovery['error'], 'data' => $recovery['data']];
}
