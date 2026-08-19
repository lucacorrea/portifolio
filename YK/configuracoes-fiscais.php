<?php

declare(strict_types=1);

$pageKey = 'configuracoes-fiscais';
$activePage = 'configuracoes-fiscais';

$pageTitle = 'Configuração Fiscal';

$pageSubtitle =
    'Certificado digital, ambiente, homologação, produção '
    . 'e integração com a SEFAZ';

$pageScripts = [
    'assets/js/configuracoes-fiscais.js',
];

$requiredAnyPermission = [
    'nota_fiscal.configurar',
    'nota_fiscal.gerenciar_credenciais',
    'nota_fiscal.testar_integracao',
    'nota_fiscal.ativar_producao',
];

$pageContent =
    __DIR__ . '/pages/configuracoes-fiscais.php';

require __DIR__ . '/includes/shell.php';
