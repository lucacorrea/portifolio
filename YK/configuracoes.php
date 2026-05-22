<?php
$pageKey = 'configuracoes';
$activePage = 'configuracoes';
$pageTitle = 'Configurações';
$pageSubtitle = 'Configure dados visuais e padrões operacionais';
$primaryActionLabel = 'Salvar Visual';
$primaryActionIcon = 'bi-check2';
$primaryActionHandler = "toast('Configurações visuais salvas localmente', 'success')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
