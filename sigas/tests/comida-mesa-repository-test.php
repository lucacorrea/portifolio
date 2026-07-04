<?php

declare(strict_types=1);

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures++;
        echo 'FAIL: ' . $message . PHP_EOL;
    }
}

$repository = file_get_contents(dirname(__DIR__) . '/app/Repositories/ComidaMesaRepository.php') ?: '';

assert_true(str_contains($repository, ':search_name'), 'pesquisa usa search_name');
assert_true(str_contains($repository, ':search_nis'), 'pesquisa usa search_nis');
assert_true(str_contains($repository, ':search_code'), 'pesquisa usa search_code');
assert_true(str_contains($repository, ':search_cpf'), 'pesquisa usa search_cpf somente quando ha numeros');
assert_true(substr_count($repository, 'p.nome LIKE :search') === 1, 'placeholder de nome nao e reutilizado');
assert_true(str_contains($repository, 'lockCompetenceForDelivery'), 'repository possui lockCompetenceForDelivery');
assert_true(str_contains($repository, 'FOR UPDATE'), 'repository usa FOR UPDATE em bloqueios transacionais');
assert_true(!str_contains($repository, 'MAX(id) + 1'), 'codigo de familia nao usa MAX(id) + 1');
assert_true(!preg_match('/DELETE\s+FROM\s+comida_mesa_entregas/i', $repository), 'entregas nao sao apagadas');

echo $failures === 0 ? 'PASS comida-mesa-repository-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
