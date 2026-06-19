<?php
declare(strict_types=1);

$pageTitle = 'Permissões do Perfil';
$pageSubtitle = 'Configure módulos e ações permitidas';
$activePage = 'perfis-acesso';
$primaryActionLabel = 'Voltar';
$primaryActionIcon = 'bi-arrow-left';
$primaryActionHref = 'perfis-acesso.php';
$requiredAllPermissions = ['perfil.visualizar', 'perfil.configurar_permissoes'];
$pageContent = __DIR__ . '/pages/perfil-permissoes.php';
$pageScripts = ['assets/js/perfis-acesso.js'];

require __DIR__ . '/includes/shell.php';
