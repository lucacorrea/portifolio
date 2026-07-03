<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Core\Csrf;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

session_id('csrf' . bin2hex(random_bytes(4)));
session_start();
$_SESSION = [];

assert_true(!Csrf::validate(null, 'login'), 'token ausente invalido');
assert_true(!Csrf::validate('', 'login'), 'token vazio invalido');
assert_true(!Csrf::validate('abc', 'login'), 'sessao sem token invalida');

$token = Csrf::token('login');

assert_true(!Csrf::validate('errado', 'login'), 'token incorreto invalido');
assert_true(Csrf::validate($token, 'login'), 'token correto valido');
assert_true(!Csrf::validate($token, 'outro'), 'token de outro formulario invalido');
assert_true(Csrf::validateAndRotate($token, 'login'), 'token correto rotaciona');
assert_true(!Csrf::validate($token, 'login'), 'token consumido nao reutiliza');

session_destroy();

echo $failures === 0 ? 'PASS csrf-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
