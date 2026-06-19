<?php

declare(strict_types=1);

$pageTitle = 'Usuários';
$pageSubtitle = 'Gerencie usuários, perfis e acessos ao sistema';
$activePage = 'usuarios';

$primaryActionLabel = 'Novo usuário';
$primaryActionIcon = 'bi-person-plus';
$primaryActionHref = 'usuario-formulario.php';
$primaryActionPermission = 'usuario.criar';

$requiredPermission = 'usuario.visualizar';

$pageContent = __DIR__ . '/pages/usuarios.php';

require __DIR__ . '/includes/shell.php';
