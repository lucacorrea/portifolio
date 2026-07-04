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
$modulePath = $root . '/modulo.php';
$convertedPages = [
    'atendimentos',
    'beneficios',
    'cadastro-anexo',
    'casa',
    'cidadania',
    'configuracoes',
    'cras1',
    'cras2',
    'creas',
    'crianca',
    'familias',
    'funeral',
    'integracao-semth',
    'manual-sistema',
    'natalidade',
    'outros',
    'perfil-usuario',
    'pessoas',
    'registro',
    'relatorios',
    'solicitacoes',
    'unidades',
    'usuarios',
];

$appJs = file_get_contents($appJsPath);
$dashboard = file_get_contents($dashboardPath);
$module = file_get_contents($modulePath);

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

if ($module === false) {
    $failures++;
    echo 'FAIL: nao foi possivel ler modulo.php' . PHP_EOL;
    $module = '';
}

assert_contains_text($appJs, "['consulta-documento.php', 'person-bounding-box', 'Consultar CPF / Registrar entrega', 'consulta']", 'sidebar aponta consulta operacional');
assert_contains_text($appJs, "['modulo.php?action=new', 'person-plus', 'Nova inscrição', 'modulo-new']", 'sidebar aponta nova inscricao operacional');
assert_contains_text($appJs, "['modulo.php', 'basket2', 'Beneficiários e competências', 'modulo', true]", 'sidebar aponta modulo operacional');
assert_contains_text($appJs, '<a href="consulta-documento.php"', 'navegacao inferior aponta consulta operacional');
assert_contains_text($appJs, '<a href="modulo.php?action=new"', 'navegacao inferior aponta nova inscricao operacional');
assert_contains_text($appJs, '<a href="modulo.php"', 'navegacao inferior aponta beneficiarios operacional');
assert_contains_text($appJs, 'href="perfil-usuario.php"', 'menu do usuario aponta perfil em PHP');
assert_contains_text($appJs, "openLink.href = 'registro.php'", 'atalho de abertura aponta registro em PHP');
assert_contains_text($appJs, 'a[href="registro.php"]', 'seletores de registro usam rota PHP');
assert_not_contains_text($appJs, '.html', 'app.js nao referencia paginas HTML antigas');
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

assert_contains_text($module, '$isNewRegistrationPage = $action === \'new\';', 'modulo reconhece pagina propria de nova inscricao');
assert_contains_text($module, 'data-registration-page', 'modulo renderiza tela dedicada para nova inscricao');
assert_contains_text($module, 'href="modulo.php?action=new"', 'modulo navega para nova inscricao por link');
assert_not_contains_text($module, 'id="newRegistrationModal"', 'modulo nao declara modal de nova inscricao');
assert_not_contains_text($module, 'data-bs-target="#newRegistrationModal"', 'modulo nao abre modal para nova inscricao');

foreach ($convertedPages as $page) {
    $path = $root . '/' . $page . '.php';
    if (!is_file($path)) {
        $failures++;
        echo "FAIL: pagina convertida nao encontrada: {$page}.php" . PHP_EOL;
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        $failures++;
        echo "FAIL: nao foi possivel ler {$page}.php" . PHP_EOL;
        continue;
    }

    assert_contains_text($content, "require_once __DIR__ . '/bootstrap.php';", "{$page}.php carrega bootstrap");
    assert_contains_text($content, 'PageContext::requireAuthenticatedFrontendContext()', "{$page}.php exige contexto autenticado");
    assert_contains_text($content, 'PageContext::script($frontendContext)', "{$page}.php injeta SIGAS_CONTEXT antes do app.js");
    assert_not_contains_text($content, '.html', "{$page}.php nao referencia paginas HTML antigas");

    $htmlPath = $root . '/' . $page . '.html';
    $htmlContent = file_get_contents($htmlPath);
    if ($htmlContent === false) {
        $failures++;
        echo "FAIL: nao foi possivel ler stub {$page}.html" . PHP_EOL;
        continue;
    }

    assert_contains_text($htmlContent, 'url=' . $page . '.php', "{$page}.html redireciona por meta refresh");
    assert_contains_text($htmlContent, "window.location.replace('{$page}.php' + window.location.search + window.location.hash)", "{$page}.html preserva query e hash no redirecionamento");
    assert_not_contains_text($htmlContent, 'id="appSidebar"', "{$page}.html nao mantem shell estatico antigo");
}

echo $failures === 0 ? 'PASS navigation-links-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
