<?php
$pageKey = 'configuracoes';
$activePage = 'configuracoes';
$pageTitle = 'Configurações';
$pageSubtitle = 'Configure dados visuais e padrões operacionais';
$primaryActionLabel = 'Salvar Visual';
$primaryActionIcon = 'bi-check2';
$primaryActionTarget = '#modal-config';
$primaryActionPermission = 'configuracao.editar';
$requiredPermission = 'configuracao.visualizar';
$pageContent = __DIR__ . '/pages/operational.php';

require __DIR__ . '/includes/shell.php';
