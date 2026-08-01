<?php
declare(strict_types=1);

$page = max(1, (int) ($_GET['page'] ?? 1));
$accessResult = ['items' => [], 'total' => 0];
$loadError = false;
try {
    $accessResult = $application->adminAccesses()->list($page, 20);
} catch (Throwable $exception) {
    error_log('Could not list administrative accesses: ' . get_class($exception));
    $loadError = true;
}

$adminContent = static function () use ($accessResult, $loadError, $page): void { ?>
<section class="admin-panel">
    <?php if ($loadError): admin_empty('Histórico indisponível', 'Não foi possível consultar a auditoria no momento.'); elseif ($accessResult['items'] === []): admin_empty('Nenhum acesso administrativo', 'Os atendimentos administrativos aparecerão aqui.'); else: ?>
    <div class="table-responsive"><table class="table os-table"><thead><tr><th>Empresa</th><th>Usuário</th><th>Motivo</th><th>IP</th><th>Início</th><th>Encerramento</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($accessResult['items'] as $access): ?><tr><td><?= admin_h($access['nome_fantasia'] ?? $access['razao_social'] ?? 'Empresa #' . $access['empresa_id']) ?></td><td><?= admin_h($access['usuario'] ?? 'Usuário #' . $access['usuario_id']) ?></td><td><?= admin_h($access['motivo'] ?? '—') ?></td><td><?= admin_h($access['ip'] ?? '—') ?></td><td><?= admin_h($access['iniciado_em'] ?? '—') ?></td><td><?= admin_h($access['encerrado_em'] ?? '—') ?></td><td><?= ($access['encerrado_em'] ?? null) ? 'Encerrado' : 'Em andamento' ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php admin_pagination($page, $accessResult['total'], 20, 'acessos.php'); ?>
    <?php endif; ?>
</section>
<?php };
