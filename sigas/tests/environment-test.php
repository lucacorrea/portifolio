<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Core\Environment;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigas_env_test_' . bin2hex(random_bytes(6));
mkdir($base, 0750, true);
$envFile = $base . DIRECTORY_SEPARATOR . '.env';

file_put_contents($envFile, "\xEF\xBB\xBF# comentario\n"
    . "SIGAS_TEST_STRING=valor\n"
    . "SIGAS_TEST_BOOL=true\n"
    . "SIGAS_TEST_INT=42\n"
    . "SIGAS_TEST_SPACE=\"valor com espaco\"\n"
    . "SIGAS_TEST_EQUALS=\"a=b=c\"\n"
    . "SIGAS_TEST_SINGLE='aspas simples'\n"
    . "SIGAS_TEST_NULL=null\n"
    . "SIGAS_TEST_SERVER=arquivo\n");

putenv('SIGAS_TEST_SERVER=servidor');
Environment::load($envFile);

assert_true(Environment::get('SIGAS_TEST_STRING') === 'valor', 'le string simples');
assert_true(Environment::bool('SIGAS_TEST_BOOL') === true, 'le booleano');
assert_true(Environment::int('SIGAS_TEST_INT') === 42, 'le inteiro');
assert_true(Environment::get('SIGAS_TEST_SPACE') === 'valor com espaco', 'preserva valor com espaco');
assert_true(Environment::get('SIGAS_TEST_EQUALS') === 'a=b=c', 'preserva valor com sinal de igual');
assert_true(Environment::get('SIGAS_TEST_SINGLE') === 'aspas simples', 'remove aspas simples externas');
assert_true(Environment::get('SIGAS_TEST_NULL') === null, 'reconhece null');
assert_true(Environment::get('SIGAS_TEST_SERVER') === 'servidor', 'nao sobrescreve variavel do servidor');
assert_true(Environment::has('SIGAS_TEST_STRING'), 'has encontra chave carregada');

$missingRequiredThrown = false;

try {
    Environment::required('SIGAS_TEST_MISSING');
} catch (Throwable) {
    $missingRequiredThrown = true;
}

assert_true($missingRequiredThrown, 'variavel obrigatoria ausente lança excecao');

$missingFileThrown = false;

try {
    Environment::load($base . DIRECTORY_SEPARATOR . 'ausente.env');
} catch (Throwable) {
    $missingFileThrown = true;
}

assert_true($missingFileThrown, 'arquivo inexistente lança excecao');

@unlink($envFile);
@rmdir($base);

echo $failures === 0 ? 'PASS environment-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
