<?php

declare(strict_types=1);

use App\Core\Application;
use App\Security\SessionManager;

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

/** @return array{0:Application,1:SessionManager,2:int} */
function cash_action_context(string $permission): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Application $application */
    $application = $app['application'];
    $session = $application->session();
    $session->start();
    try {
        $application->csrf()->requireValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
        $authorization = $application->authorization();
        $user = $authorization->requireLogin();
        $authorization->requirePermission($permission);
        return [$application, $session, (int) $user->id()];
    } catch (Throwable $exception) {
        error_log('Cash action access failed: ' . $exception->getMessage());
        $session->flash('danger', 'Não foi possível validar a operação do Caixa.');
        cash_action_redirect($application);
    }
}

function cash_action_positive_int(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($id)) throw new InvalidArgumentException('Identificador inválido.');
    return $id;
}

function cash_action_redirect(Application $application): never
{
    header('Location: ' . $application->redirect()->applicationUrl(action_return_target($application, 'caixa.php')), true, 303);
    exit;
}
