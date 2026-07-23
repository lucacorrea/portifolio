<?php

declare(strict_types=1);

function fiscalUiAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$actions = [
    'configuracao-fiscal-certificado-salvar.php' => 'nota_fiscal.gerenciar_credenciais',
    'configuracao-fiscal-salvar.php' => 'nota_fiscal.configurar',
    'configuracao-fiscal-serie-salvar.php' => 'nota_fiscal.configurar',
    'configuracao-fiscal-ativar.php' => 'nota_fiscal.configurar',
    'configuracao-fiscal-testar-sefaz.php' => 'nota_fiscal.testar_integracao',
];
foreach ($actions as $file => $permission) {
    $source = file_get_contents($root . '/actions/' . $file);
    fiscalUiAssert(is_string($source), 'A ação fiscal deve existir.');
    fiscalUiAssert(str_contains($source, 'os_require_post_request()'), 'A ação fiscal deve aceitar apenas POST.');
    fiscalUiAssert(str_contains($source, "os_action_context('" . $permission . "')"), 'A ação fiscal deve exigir sua permissão específica e CSRF.');
    fiscalUiAssert(!str_contains($source, "error_log('" . '$exception->getMessage()'), 'Erros fiscais não podem registrar detalhes potencialmente sensíveis.');
}

$page = file_get_contents($root . '/pages/configuracoes-fiscais.php');
fiscalUiAssert(is_string($page) && str_contains($page, 'autocomplete="new-password"'), 'Segredos fiscais devem usar campos sem recuperação automática.');
fiscalUiAssert(!str_contains($page, "['csc_ciphertext']") && !str_contains($page, "['senha_ciphertext']"), 'A tela nunca pode renderizar material cifrado.');
fiscalUiAssert(str_contains($page, 'Testar comunicação com a SEFAZ'), 'A configuração deve expor o teste real de homologação.');
fiscalUiAssert(str_contains($page, "\$integrationTest['success']"), 'A ativação deve depender do último teste SEFAZ bem-sucedido.');
$certificateScript = file_get_contents($root . '/assets/js/configuracoes-fiscais.js');
fiscalUiAssert(
    is_string($certificateScript) && str_contains($certificateScript, 'AbortController'),
    'O envio do certificado deve encerrar o carregamento quando o servidor não responder.'
);

$billing = file_get_contents($root . '/pages/faturamento.php');
fiscalUiAssert(is_string($billing) && str_contains($billing, 'A tela não simula notas fiscais.'), 'Faturamento não deve apresentar notas fictícias como reais.');

$htaccess = file_get_contents($root . '/.htaccess');
fiscalUiAssert(is_string($htaccess) && str_contains($htaccess, 'RewriteCond %{HTTPS} !=on'), 'O sistema deve redirecionar HTTP para HTTPS.');
fiscalUiAssert(str_contains((string) $htaccess, 'Strict-Transport-Security'), 'HTTPS deve publicar HSTS.');
fiscalUiAssert(str_contains((string) $htaccess, 'frame-ancestors \'none\''), 'Telas sensíveis devem bloquear incorporação em frames.');

echo "Fiscal UI security tests passed.\n";
