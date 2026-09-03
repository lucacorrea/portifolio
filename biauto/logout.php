<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_validate();
audit($pdo, 'usuarios', (int) $_SESSION['usuario_id'], 'logout');
encerrar_sessao();
redirect('login.php');
