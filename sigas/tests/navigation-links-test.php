<?php

declare(strict_types=1);

$failures = 0;

function assert_contains_text(string $haystack, string $needle, string $message): void
{
    global $failures;

    if (!str_contains($haystack, $needle)) {
        $failures++;
        echo "FAIL: {$message}. Nao encontrou: {$needle}" . PHP_EOL;
    }
}

function assert_not_contains_text(string $haystack, string $needle, string $message): void
{
    global $failures;

    if (str_contains($haystack, $needle)) {
        $failures++;
        echo "FAIL: {$message}. Encontrou trecho antigo: {$needle}" . PHP_EOL;
    }
}

$root = dirname(__DIR__);
$appJsPath = $root . '/assets/js/app.js';
$dashboardPath = $root . '/dashboard.php';

$appJs = file_get_contents($appJsPath);
$dashboard = file_get_contents($dashboardPath);

if ($appJs === false) {
    $failures++;
    echo 'FAIL: nao foi possivel ler assets/js/app.js' . PHP_EOL;
    $appJs = '';
}

if ($dashboard === false) {
    $failures++;
    echo 'FAIL: nao foi possivel ler dashboard.php' . PHP_EOL;
    $dashboard = '';
}

assert_contains_text($appJs, "['consulta-documento.php', 'person-bounding-box', 'Consultar CPF / Registrar entrega', 'consulta']", 'sidebar aponta consulta operacional');
assert_contains_text($appJs, "['modulo.php?action=new', 'person-plus', 'Nova inscrição', 'modulo-new']", 'sidebar aponta nova inscricao operacional');
assert_contains_text($appJs, "['modulo.php', 'basket2', 'Beneficiários e competências', 'modulo', true]", 'sidebar aponta modulo operacional');
assert_contains_text($appJs, '<a href="consulta-documento.php"', 'navegacao inferior aponta consulta operacional');
assert_contains_text($appJs, '<a href="modulo.php?action=new"', 'navegacao inferior aponta nova inscricao operacional');
assert_contains_text($appJs, '<a href="modulo.php"', 'navegacao inferior aponta beneficiarios operacional');
assert_not_contains_text($appJs, "window.location.href = 'cadastro-anexo.html'", 'atalho novo nao redireciona para cadastro-anexo antigo');
assert_not_contains_text($appJs, "window.location.replace('cadastro-anexo.html')", 'hash novo nao redireciona para cadastro-anexo antigo');

assert_contains_text($dashboard, 'href="consulta-documento.php"', 'dashboard oferece consulta operacional');
assert_contains_text($dashboard, 'href="modulo.php?action=new"', 'dashboard oferece nova inscricao operacional');
assert_contains_text($dashboard, 'href="modulo.php"', 'dashboard oferece abertura do modulo operacional');
assert_contains_text($dashboard, 'Consultar e registrar entrega', 'cartao Comida na Mesa aponta para consulta e entrega');
assert_contains_text($dashboard, 'Nova inscrição', 'atalhos rapidos exibem nova inscricao');
assert_contains_text($dashboard, 'Consultar CPF / Entrega', 'atalhos rapidos exibem consulta CPF e entrega');
assert_contains_text($dashboard, '<span>Registrar entrega</span>', 'atalhos rapidos exibem registrar entrega como link funcional');
assert_not_contains_text($dashboard, 'href="cadastro-anexo.html"', 'dashboard nao exibe link antigo de cadastro-anexo');
assert_not_contains_text($dashboard, 'href="pessoas.html"', 'dashboard nao exibe link antigo de pessoas');
assert_not_contains_text($dashboard, 'href="solicitacoes.html"', 'dashboard nao exibe link antigo de solicitacoes');
assert_not_contains_text($dashboard, 'href="atendimentos.html"', 'dashboard nao exibe link antigo de atendimentos');
assert_not_contains_text($dashboard, 'data-bs-target="#deliveryModal"', 'dashboard nao abre modal ficticio de entrega');
assert_not_contains_text($dashboard, 'id="deliveryModal"', 'dashboard nao declara modal ficticio de entrega');
assert_not_contains_text($dashboard, 'CM-000125', 'dashboard nao mantem codigo ficticio no fluxo de entrega');
assert_not_contains_text($dashboard, 'Maria da Silva', 'dashboard nao mantem recebedor ficticio no fluxo de entrega');
assert_not_contains_text($dashboard, 'Entrega confirmada com sucesso', 'dashboard nao mantem submit demo de entrega');

echo $failures === 0 ? 'PASS navigation-links-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
