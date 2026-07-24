<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Security/SafeRedirect.php';

use App\Security\SafeRedirect;

function redirectAssertSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . $expected . '; obtido: ' . $actual);
    }
}

$redirect = new SafeRedirect();

$menuSource = file_get_contents(dirname(__DIR__) . '/includes/menu.php');
if (!is_string($menuSource)) {
    throw new RuntimeException('Não foi possível ler o menu principal.');
}
preg_match_all("/'href'\\s*=>\\s*'([^']+)'/", $menuSource, $menuMatches);
foreach (array_unique($menuMatches[1] ?? []) as $menuTarget) {
    redirectAssertSame($menuTarget, $redirect->sanitize($menuTarget), 'Toda página do menu deve ser um retorno interno permitido.');
}

foreach (['fornecedores.php', 'contas-pagar.php', 'usuarios.php', 'relatorios.php', 'frente-caixa.php', 'caixa-vendas.php', 'caixa-movimentacoes.php'] as $page) {
    $target = $page . '?search=teste&page=2';
    redirectAssertSame($target, $redirect->sanitize($target), 'A página operacional deve preservar filtros no retorno.');
    redirectAssertSame('/YK/' . $target, $redirect->applicationUrl($target), 'A URL interna deve retornar à página de origem.');
}

foreach ([
    'https://example.com',
    '../usuarios.php',
    '/usuarios.php',
    'pagina-inexistente.php',
    "usuarios.php\r\nLocation:%20https://example.com",
    'usuarios.php%0d%0aLocation:%20https://example.com',
    "usuarios.php\t?search=teste",
] as $unsafe) {
    redirectAssertSame('dashboard.php', $redirect->sanitize($unsafe), 'Destino não autorizado deve continuar bloqueado.');
}

echo "SafeRedirectTest: OK\n";
