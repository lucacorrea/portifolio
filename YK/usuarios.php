<?php

declare(strict_types=1);

$pageTitle = 'Usuários';
$pageSubtitle = 'Gerencie usuários, perfis e acessos ao sistema';
$activePage = 'usuarios';

/*
 * O botão principal do topo abrirá o modal de cadastro.
 * Não utilizar primaryActionHref nesta página.
 */
$primaryActionLabel = 'Novo usuário';
$primaryActionIcon = 'bi-person-plus';
$primaryActionTarget = '#modal-usuario';
$primaryActionPermission = 'usuario.criar';

$requiredPermission = 'usuario.visualizar';

$pageContent = __DIR__ . '/pages/usuarios.php';

require __DIR__ . '/includes/shell.php';
