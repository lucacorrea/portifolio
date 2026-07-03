<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Core\Validator;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

assert_true(Validator::cpf('529.982.247-25'), 'CPF valido');
assert_true(!Validator::cpf('111.111.111-11'), 'CPF repetido invalido');
assert_true(!Validator::cpf('123.456.789-00'), 'CPF com digito invalido');
assert_true(Validator::email('usuario@exemplo.gov.br'), 'email valido');
assert_true(!Validator::email('usuario@@exemplo'), 'email invalido');
assert_true(Validator::strongPassword('Senha@123'), 'senha forte');
assert_true(!Validator::strongPassword('senhafraca'), 'senha fraca');
assert_true(Validator::positiveInt('1'), 'inteiro positivo');
assert_true(!Validator::positiveInt('0'), 'zero nao e positivo');
assert_true(Validator::userStatus('ativo'), 'status permitido');
assert_true(!Validator::userStatus('suspenso'), 'status nao permitido');

echo $failures === 0 ? 'PASS validator-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
