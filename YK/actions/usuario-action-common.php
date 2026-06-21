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
    $decodedTarget = rawurldecode(
        trim($target)
    );

    $targetPath = parse_url(
        $decodedTarget,
        PHP_URL_PATH
    );

    if (
        $decodedTarget !== ''
        && $targetPath === 'usuarios.php'
        && !str_contains($decodedTarget, "\0")
        && !str_contains($decodedTarget, '..')
        && !str_starts_with($decodedTarget, '/')
        && !str_starts_with($decodedTarget, '\\')
        && !str_starts_with($decodedTarget, '//')
        && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $decodedTarget)
    ) {
        $basePath = rtrim(
            dirname(
                $application
                    ->redirect()
                    ->loginUrl()
            ),
            '/\\'
        );

        header(
            'Location: '
            . $basePath
            . '/'
            . $decodedTarget,
            true,
            303
        );

        exit;
    }

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
 * Guarda dados seguros para reabrir um modal após erro de validação.
 */
function user_store_form_recovery(
    string $modal,
    array $data,
    string $error
): void {
    $blockedKeys = [
        'password',
        'password_confirmation',
        'senha_hash',
        'csrf_token',
    ];

    $safeData = [];

    foreach ($data as $key => $value) {
        $key = (string) $key;

        if (in_array($key, $blockedKeys, true)) {
            continue;
        }

        if (is_scalar($value) || $value === null) {
            $safeData[$key] = $value;
        }
    }

    $_SESSION['user_form_recovery'] = [
        'modal' => $modal,
        'error' => $error,
        'data' => $safeData,
    ];
}

/**
 * Recupera uma única vez os dados seguros do modal.
 *
 * @return array{
 *     modal:string,
 *     error:string,
 *     data:array<string, mixed>
 * }|null
 */
function user_consume_form_recovery(): ?array
{
    if (
        !isset($_SESSION['user_form_recovery'])
        || !is_array($_SESSION['user_form_recovery'])
    ) {
        unset($_SESSION['user_form_recovery']);

        return null;
    }

    $recovery = $_SESSION['user_form_recovery'];

    unset($_SESSION['user_form_recovery']);

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
