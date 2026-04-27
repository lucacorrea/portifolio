<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

$empresaId = (int) ($_POST['empresa_id'] ?? 0);
$status = $_POST['status'] ?? 'bloqueada';

if ($empresaId <= 0) {
    flash('error', 'Empresa inválida.');
    redirect('/admin/empresas.php');
}

if (!in_array($status, ['teste', 'ativa', 'bloqueada', 'cancelada'], true)) {
    $status = 'bloqueada';
}

db()->prepare('UPDATE empresas SET status = :status WHERE id = :id')
    ->execute([':status' => $status, ':id' => $empresaId]);

flash('success', 'Status da empresa atualizado.');
redirect('/admin/empresas.php');
