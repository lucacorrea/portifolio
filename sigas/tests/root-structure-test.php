<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function root_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

foreach (['index.php', 'portal.php', 'sair.php', 'bootstrap.php'] as $required) {
    root_assert(is_file($root . '/' . $required), 'arquivo estrutural obrigatório ausente: ' . $required);
}

$obsoletePhp = [
    'administracao.php',
    'dashboard.php',
    'usuarios.php',
    'configuracoes.php',
    'unidades.php',
    'perfil-usuario.php',
    'manual-sistema.php',
];

foreach ($obsoletePhp as $file) {
    root_assert(!is_file($root . '/' . $file), 'página administrativa legada voltou para a raiz: ' . $file);
}

$entries = scandir($root) ?: [];
foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }

    root_assert(!str_ends_with(strtolower($entry), '.zip'), 'arquivo ZIP não deve permanecer na raiz: ' . $entry);

    if ($entry !== 'README.md') {
        root_assert(
            !preg_match('/^(LEIA-ME|ARQUIVOS_ALTERADOS|ALTERACOES_|ARQUITETURA_|PRIMEIRO_EMPREGO_)/i', $entry),
            'anotação/hotfix legado não deve permanecer na raiz: ' . $entry
        );
    }
}

$htaccess = file_get_contents($root . '/.htaccess') ?: '';
foreach (['app', 'database', 'frontend', 'tests', 'docs', 'scripts', 'instalacao'] as $protectedDirectory) {
    root_assert(str_contains($htaccess, $protectedDirectory), 'diretório interno deve estar protegido no .htaccess: ' . $protectedDirectory);
}
root_assert(str_contains($htaccess, '<Files "bootstrap.php">'), 'bootstrap.php deve ter bloqueio de acesso HTTP direto');
root_assert(str_contains($htaccess, 'md|txt|zip'), 'documentos e ZIPs devem estar bloqueados pelo .htaccess');

if ($failures === []) {
    echo 'PASS root-structure-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
