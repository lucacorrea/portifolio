<?php
$pageKey = 'configuracoes';
$activePage = 'configuracoes';
$pageTitle = 'Configurações';
$pageSubtitle = 'Dados empresariais usados em documentos';
$requiredPermission = 'configuracao.visualizar';
$pageContent = __DIR__ . '/pages/configuracoes.php';
$pageScripts = ['assets/js/configuracoes.js'];

require __DIR__ . '/includes/shell.php';
