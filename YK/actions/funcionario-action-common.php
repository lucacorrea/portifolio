<?php

declare(strict_types=1);

use App\Access\DTO\AuthenticatedUser;
use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;
use App\Security\SessionManager;

if (
    basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))
    === basename(__FILE__)
) {
    http_response_code(404);
    exit;
}

/**
 * @return array{
 *     0: Application,
 *     1: SessionManager,
 *     2: AuthenticatedUser
 * }
 */
function employee_action_context(
    string $permission
): array {
    $app = require dirname(__DIR__) . '/bootstrap.php';

    /** @var Application $application */
    $application = $app['application'];

    $session = $application->session();
    $session->start();

    try {
        $csrfToken = isset($_POST['csrf_token'])
            ? (string) $_POST['csrf_token']
            : null;

        $application
            ->csrf()
            ->requireValid($csrfToken);
    } catch (Throwable $exception) {
        error_log(
            'Employee action CSRF failed: '
            . $exception->getMessage()
        );

        http_response_code(403);
        exit;
    }

    try {
        $authorization = $application->authorization();
        $currentUser = $authorization->requireLogin();
        $authorization->requirePermission($permission);
    } catch (AuthenticationException $exception) {
        $session->flash(
            'warning',
            'Sua sessão expirou. Entre novamente.'
        );

        header(
            'Location: '
            . $application
                ->redirect()
                ->loginUrl(),
            true,
            303
        );

        exit;
    } catch (AuthorizationException $exception) {
        header(
            'Location: '
            . $application
                ->redirect()
                ->applicationUrl('acesso-negado.php'),
            true,
            303
        );

        exit;
    }

    return [
        $application,
        $session,
        $currentUser,
    ];
}

function employee_redirect(
    Application $application,
    string $target = 'funcionarios.php'
): never {
    header(
        'Location: '
        . $application
            ->redirect()
            ->applicationUrl($target),
        true,
        303
    );

    exit;
}

function employee_posted_positive_int(
    string $key
): int {
    $value = filter_input(
        INPUT_POST,
        $key,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    if (!is_int($value)) {
        throw new InvalidArgumentException(
            'Identificador inválido.'
        );
    }

    return $value;
}

function employee_require_post_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
}

function employee_store_form_recovery(
    string $modal,
    array $data,
    string $error
): void {
    unset($data['csrf_token']);

    $_SESSION['employee_form_recovery'] = [
        'modal' => $modal,
        'error' => $error,
        'data' => array_filter(
            $data,
            static fn(mixed $value): bool =>
                is_scalar($value)
                || $value === null
        ),
    ];
}

/**
 * @return array{
 *     modal:string,
 *     error:string,
 *     data:array<string, mixed>
 * }|null
 */
function employee_consume_form_recovery(): ?array
{
    if (
        !isset($_SESSION['employee_form_recovery'])
        || !is_array($_SESSION['employee_form_recovery'])
    ) {
        unset($_SESSION['employee_form_recovery']);

        return null;
    }

    $recovery = $_SESSION['employee_form_recovery'];
    unset($_SESSION['employee_form_recovery']);

    if (
        !isset($recovery['modal'], $recovery['error'], $recovery['data'])
        || !is_string($recovery['modal'])
        || !is_string($recovery['error'])
        || !is_array($recovery['data'])
    ) {
        return null;
    }

    return [
        'modal' => $recovery['modal'],
        'error' => $recovery['error'],
        'data' => $recovery['data'],
    ];
}
