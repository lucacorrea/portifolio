<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Core\Csrf;

$failures = [];
$sessionDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigas_csrf_test_' . bin2hex(random_bytes(6));

function fail(string $message): void
{
    global $failures;
    $failures[] = $message;
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

function remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path) ?: [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = $path . DIRECTORY_SEPARATOR . $item;
        is_dir($full) ? remove_tree($full) : @unlink($full);
    }

    @rmdir($path);
}

mkdir($sessionDir, 0700, true);
ini_set('session.save_path', $sessionDir);
ini_set('session.use_cookies', '0');
session_cache_limiter('');
session_name('SIGAS_CSRF_TEST_' . bin2hex(random_bytes(4)));
session_id('csrf' . bin2hex(random_bytes(8)));

try {
    $_POST = [];

    assert_true(!Csrf::validate($_POST['_csrf'] ?? null, 'login'), 'token ausente retorna false');
    assert_true(!Csrf::validate(null, 'login'), 'token null retorna false');
    assert_true(!Csrf::validate('', 'login'), 'token vazio retorna false');
    assert_true(!Csrf::validate('   ', 'login'), 'token com espacos retorna false');
    assert_true(!Csrf::validate('abc', 'login'), 'sessao sem token retorna false');

    $loginToken = Csrf::token('login');
    $profileToken = Csrf::token('profile');

    assert_true(!Csrf::validate('token-incorreto', 'login'), 'token incorreto retorna false');
    assert_true(Csrf::validate($loginToken, 'login'), 'token correto retorna true');
    assert_true(!Csrf::validate($loginToken, 'profile'), 'token de um formulario nao funciona em outro');

    $oldRotateToken = Csrf::token('rotate');
    $newRotateToken = Csrf::rotate('rotate');

    assert_true($oldRotateToken !== $newRotateToken, 'rotate gera novo token');
    assert_true(!Csrf::validate($oldRotateToken, 'rotate'), 'rotate invalida o token anterior');
    assert_true(Csrf::validate($newRotateToken, 'rotate'), 'token rotacionado atual permanece valido');

    $consumeToken = Csrf::token('consume');

    assert_true(Csrf::validateAndConsume($consumeToken, 'consume'), 'validateAndConsume aceita token correto');
    assert_true(!Csrf::validate($consumeToken, 'consume'), 'token consumido nao pode ser usado novamente');

    assert_true(Csrf::validateAndConsume($profileToken, 'profile'), 'consumir um formulario funciona');
    assert_true(Csrf::validate($loginToken, 'login'), 'consumir um token nao remove token de outro formulario');

    $hiddenInput = Csrf::input('login');

    assert_true(str_contains($hiddenInput, 'type="hidden"'), 'input produz campo oculto');
    assert_true(str_contains($hiddenInput, 'name="_csrf"'), 'input produz campo _csrf');

    $_SESSION['_csrf_tokens']['escaped'] = '"<script>&';
    $escapedInput = Csrf::input('escaped');

    assert_true(str_contains($escapedInput, '&quot;&lt;script&gt;&amp;'), 'input escapa corretamente o valor');
    assert_true(!str_contains($escapedInput, '"<script>&'), 'input nao contem valor sem escape');
} catch (Throwable $exception) {
    fail('excecao inesperada: ' . $exception::class);
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }

    remove_tree($sessionDir);
}

if ($failures === []) {
    echo 'PASS csrf-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
