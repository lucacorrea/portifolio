<?php
declare(strict_types=1);

$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$result = $application->adminCompanies()->list(['search' => $search, 'status' => $status], $page, 20);
$schemaReady = $application->adminCompanies()->ready();

$adminContent = static function () use ($result, $page, $search, $status, $schemaReady): void { ?>
<section class="admin-panel">
    <?php if (!$schemaReady): ?><div class="alert alert-danger" role="alert">A estrutura administrativa do banco ainda não foi atualizada. Execute a migration 024 antes de cadastrar empresas.</div><?php endif; ?>
    <div class="admin-panel-heading">
        <form class="admin-filters" method="get">
            <label class="visually-hidden" for="company-search">Buscar empresa</label><input id="company-search" name="q" value="<?= admin_h($search) ?>" placeholder="Buscar empresa">
            <label class="visually-hidden" for="company-status">Status</label><select id="company-status" name="status"><option value="">Todos os status</option><?php foreach (['pendente' => 'Pendente', 'ativo' => 'Ativa', 'inativo' => 'Inativa', 'bloqueado' => 'Bloqueada'] as $key => $label): ?><option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary" type="submit">Filtrar</button>
        </form>
        <a class="btn btn-primary" href="<?= admin_url('empresa.php?nova=1') ?>"><i class="bi bi-plus-lg"></i> Nova empresa</a>
    </div>
    <?php if ($result['items'] === []): admin_empty('Nenhuma empresa encontrada', 'Ajuste os filtros ou cadastre uma nova empresa.'); else: ?>
        <div class="table-responsive"><table class="table os-table"><thead><tr><th>Empresa</th><th>Documento</th><th>Segmento</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($result['items'] as $company): ?><tr><td><?= admin_h($company['nome_fantasia'] ?? $company['razao_social'] ?? '—') ?></td><td><?= admin_h($company['documento'] ?? '—') ?></td><td><?= admin_h($company['segmento'] ?? '—') ?></td><td><?= admin_badge((string) ($company['status'] ?? 'pendente')) ?></td><td><div class="dropdown table-action-dropdown"><button class="btn-action" data-bs-toggle="dropdown" aria-label="Ações da empresa"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="<?= admin_url('empresa.php?id=' . (int) $company['id']) ?>">Visualizar e editar</a></li></ul></div></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php admin_pagination($page, $result['total'], 20, 'empresas.php', ['q' => $search, 'status' => $status]); ?>
    <?php endif; ?>
</section>
<?php };
