<?php
declare(strict_types=1);
$pageTitle='Empresas'; $pageSubtitle='Administração das empresas da plataforma'; $activePage='admin-empresas'; $showPrimaryAction=false; $requiredPermission='configuracao.visualizar'; $platformAdminOnly=true; $pageStyles=['assets/css/admin-empresas.css']; $pageScripts=['assets/js/admin-empresas.js']; $pageContent=__DIR__.'/pages/admin-empresas.php'; require __DIR__.'/includes/shell.php';
