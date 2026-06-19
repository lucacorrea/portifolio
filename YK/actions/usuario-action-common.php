<?php

declare(strict_types=1);

use App\Access\DTO\AuthenticatedUser;
use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;
use App\Security\SessionManager;

/*
 * Impede o acesso direto a este arquivo auxiliar.
 */
if (
    basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))
    === basename(__FILE__)
) {
    http_response_code(404);
    exit;
}

/**
 * Inicializa o contexto seguro das ações de usuário.
 *
 * @return array{
 *     0: Application,
 *     1: SessionManager,
 *     2: AuthenticatedUser
 * }
 */
function user_action_context(
    string $permission
): array {
    $app = require dirname(__DIR__) . '/bootstrap.php';

    /** @var Application $application */
    $application = $app['application'];

    $session = $application->session();
    $session->start();

    /*
     * Validação CSRF antes de processar qualquer ação.
     */
    try {
        $csrfToken = isset($_POST['csrf_token'])
            ? (string) $_POST['csrf_token']
            : null;

        $application
            ->csrf()
            ->requireValid($csrfToken);
    } catch (Throwable $exception) {
        error_log(
            'User action CSRF failed: '
            . $exception->getMessage()
        );

        http_response_code(403);
        exit;
    }

    try {
        $authorization = $application->authorization();

        $currentUser = $authorization->requireLogin();

        $authorization->requirePermission(
            $permission
        );
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
                ->applicationUrl(
                    'acesso-negado.php'
                ),
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

/**
 * Realiza redirecionamento interno seguro.
 */
function user_redirect(
    Application $application,
    string $target = 'usuarios.php'
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

/**
 * Lê um ID positivo enviado via POST.
 */
function user_posted_positive_int(
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

/**
 * Lê campos booleanos de formulário.
 *
 * Aceita:
 * 1, true, on, yes e sim.
 */
function user_posted_bool(
    string $key,
    bool $default = false
): bool {
    if (!array_key_exists($key, $_POST)) {
        return $default;
    }

    $value = $_POST[$key];

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value === 1;
    }

    $normalized = strtolower(
        trim((string) $value)
    );

    return in_array(
        $normalized,
        [
            '1',
            'true',
            'on',
            'yes',
            'sim',
        ],
        true
    );
}

/**
 * Confirma que a requisição foi enviada por POST.
 */
function user_require_post_request(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '')
        !== 'POST'
    ) {
        http_response_code(405);

        header(
            'Allow: POST'
        );

        exit;
    }
}
