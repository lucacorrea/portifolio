<?php

declare(strict_types=1);

$pageTitle = 'Produtos / Peças';
$pageSubtitle = 'Cadastro de produtos, peças e controle básico de estoque';
$activePage = 'pecas';

$primaryActionLabel = 'Novo produto';
$primaryActionIcon = 'bi-box-seam';
$primaryActionTarget = '#modal-produto';
$primaryActionPermission = 'produto.criar';

$requiredPermission = 'produto.visualizar';
$pageContent = __DIR__ . '/pages/produtos.php';

require __DIR__ . '/includes/shell.php';
