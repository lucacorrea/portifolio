<?php
declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

function profile_action_context(string $permission): array
{
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Application $application */
    $application = $app['application'];
    $session = $application->session();
    $session->start();

    try {
        $application->csrf()->requireValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
    } catch (Throwable $exception) {
        http_response_code(403);
        exit;
    }

    try {
        $authorization = $application->authorization();
        $authorization->requireLogin();
        $authorization->requirePermission($permission);
    } catch (AuthenticationException $exception) {
        $session->flash('warning', 'Sua sessão expirou. Entre novamente.');
        header('Location: ' . $application->redirect()->loginUrl(), true, 303);
        exit;
    } catch (AuthorizationException $exception) {
        header('Location: ' . $application->redirect()->applicationUrl('acesso-negado.php'), true, 303);
        exit;
    }

    return [$application, $session];
}

function profile_redirect(Application $application, string $target): never
{
    header('Location: ' . $application->redirect()->applicationUrl(action_return_target($application, $target)), true, 303);
    exit;
}

function posted_positive_int(string $key): int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if (!is_int($value)) {
        throw new InvalidArgumentException('ID invalido.');
    }

    return $value;
}

/**
 * @return int[]
 */
function posted_int_array(string $key): array
{
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0));
}
