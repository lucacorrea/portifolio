<?php
declare(strict_types=1);
$pageTitle='Painel da empresa'; $pageSubtitle='Contexto administrativo da empresa selecionada'; $activePage='admin-empresas'; $showPrimaryAction=false; $requiredPermission='configuracao.visualizar'; $platformAdminOnly=true; $pageStyles=['assets/css/admin-empresas.css']; $pageContent=__DIR__.'/pages/empresa-painel.php'; require __DIR__.'/includes/shell.php';
