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

$allowedRootPhp = [
    'bootstrap.php',
    'historico-pessoa.php',
    'index.php',
    'portal.php',
    'prontuario-socioeconomico.php',
    'sair.php',
    'setor.php',
];

foreach ($allowedRootPhp as $required) {
    root_assert(is_file($root . '/' . $required), 'arquivo estrutural obrigatório ausente: ' . $required);
}

$rootPhp = [];
foreach (scandir($root) ?: [] as $entry) {
    if (str_ends_with(strtolower($entry), '.php') && is_file($root . '/' . $entry)) {
        $rootPhp[] = $entry;
    }
}
sort($rootPhp);
$expectedPhp = $allowedRootPhp;
sort($expectedPhp);
root_assert(
    $rootPhp === $expectedPhp,
    'a raiz deve conter somente PHPs transversais. Atual: ' . implode(', ', $rootPhp)
);

$obsoletePhp = [
    'administracao.php',
    'atendimentos.php',
    'beneficios.php',
    'cadastro-anexo.php',
    'casa.php',
    'cidadania.php',
    'configuracoes.php',
    'consulta-documento.php',
    'cras1.php',
    'cras2.php',
    'creas.php',
    'crianca.php',
    'dashboard.php',
    'familias.php',
    'funeral.php',
    'integracao-semth.php',
    'manual-sistema.php',
    'modulo.php',
    'natalidade.php',
    'outros.php',
    'perfil-usuario.php',
    'pessoas.php',
    'registro.php',
    'relatorios.php',
    'solicitacoes.php',
    'unidades.php',
    'usuarios.php',
];

foreach ($obsoletePhp as $file) {
    root_assert(!is_file($root . '/' . $file), 'página legada voltou para a raiz: ' . $file);
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

foreach ([
    'pessoas|familias|cadastro-anexo|registro|integracao-semth',
    'atendimentos|solicitacoes',
    'beneficios|natalidade|funeral|outros',
    'consulta-documento',
    'modulo',
    'cras1|cras2|creas|casa|cidadania|crianca|relatorios',
] as $redirectGroup) {
    root_assert(str_contains($htaccess, $redirectGroup), 'redirect de compatibilidade ausente: ' . $redirectGroup);
}

if ($failures === []) {
    echo 'PASS root-structure-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
