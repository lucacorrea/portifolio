<?php
declare(strict_types=1);

$page = max(1, (int) ($_GET['page'] ?? 1));
$result = $application->adminCompanies()->list([], $page, 20, $currentUser->id());
$counts = $application->adminCompanies()->counts($currentUser->id());
$adminContent = static function () use ($result, $page, $counts): void { ?>
<div class="admin-metrics compact">
    <article class="admin-metric"><span>Cadastradas por mim</span><strong><?= $counts['mine'] ?></strong></article>
    <article class="admin-metric"><span>Ativas na plataforma</span><strong><?= $counts['ativo'] ?></strong></article>
    <article class="admin-metric"><span>Vinculadas ao SO</span><strong><?= $counts['so'] ?></strong></article>
</div>
<?php if ($result['items'] === []): admin_empty('Nenhum cadastro', 'Você ainda não cadastrou empresas.'); else: ?>
<section class="admin-panel"><div class="table-responsive"><table class="table os-table"><thead><tr><th>Empresa</th><th>Documento</th><th>Segmento</th><th>Status</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($result['items'] as $company): ?><tr><td><?= admin_h($company['nome_fantasia'] ?? $company['razao_social'] ?? '—') ?></td><td><?= admin_h($company['documento'] ?? '—') ?></td><td><?= admin_h($company['segmento'] ?? '—') ?></td><td><?= admin_badge((string) ($company['status'] ?? 'pendente')) ?></td><td><a href="<?= admin_url('empresa.php?id=' . (int) $company['id']) ?>">Visualizar</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php admin_pagination($page, $result['total'], 20, 'minhas-aprovacoes.php'); ?></section>
<?php endif;
};
