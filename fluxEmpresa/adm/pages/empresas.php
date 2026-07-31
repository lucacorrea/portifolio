<?php
declare(strict_types=1);

$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$result = $application->adminCompanies()->list(['search' => $search, 'status' => $status], $page, 20);
$schemaReady = $application->adminCompanies()->ready();

$adminContent = static function () use ($result, $page, $search, $status, $schemaReady, $csrf): void { ?>
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
        <div class="table-responsive">
            <table class="table os-table">
                <thead><tr><th>Empresa</th><th>Documento</th><th>Segmento</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $company):
                    $companyId = (int) $company['id'];
                    $companyName = (string) ($company['nome_fantasia'] ?? $company['razao_social'] ?? 'Empresa');
                    $canOpenOperationalPanel = (string) ($company['status'] ?? '') === 'ativo';
                    $accessModalId = 'company-operational-access-' . $companyId;
                ?>
                    <tr>
                        <td><?= admin_h($companyName) ?></td>
                        <td><?= admin_h($company['documento'] ?? '—') ?></td>
                        <td><?= admin_h($company['segmento'] ?? '—') ?></td>
                        <td><?= admin_badge((string) ($company['status'] ?? 'pendente')) ?></td>
                        <td>
                            <div class="dropdown table-action-dropdown">
                                <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da empresa <?= admin_h($companyName) ?>"><i class="bi bi-three-dots"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= admin_url('empresa.php?id=' . $companyId) ?>"><i class="bi bi-pencil-square"></i> Visualizar e editar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button
                                            class="dropdown-item"
                                            type="button"
                                            <?= $canOpenOperationalPanel ? 'data-bs-toggle="modal" data-bs-target="#' . $accessModalId . '"' : 'disabled aria-disabled="true" title="Disponível somente para empresas ativas"' ?>
                                        ><i class="bi bi-box-arrow-in-right"></i> Entrar no painel operacional</button>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php foreach ($result['items'] as $company):
            if ((string) ($company['status'] ?? '') !== 'ativo') continue;
            $companyId = (int) $company['id'];
            $companyName = (string) ($company['nome_fantasia'] ?? $company['razao_social'] ?? 'Empresa');
            $accessModalId = 'company-operational-access-' . $companyId;
        ?>
            <div class="modal fade admin-company-access-modal" id="<?= $accessModalId ?>" tabindex="-1" aria-labelledby="<?= $accessModalId ?>-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="post" action="<?= admin_url('actions/empresa-entrar.php') ?>">
                            <?= $csrf->field() ?>
                            <input type="hidden" name="id" value="<?= $companyId ?>">
                            <div class="modal-header">
                                <div>
                                    <span class="admin-access-eyebrow">Acesso administrativo</span>
                                    <h2 class="modal-title fs-5" id="<?= $accessModalId ?>-title">Entrar no painel de <?= admin_h($companyName) ?></h2>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <p>Informe o motivo do acesso. A entrada será registrada no histórico administrativo.</p>
                                <label class="form-label" for="<?= $accessModalId ?>-reason">Motivo ou chamado</label>
                                <textarea class="form-control" id="<?= $accessModalId ?>-reason" name="motivo" minlength="10" maxlength="255" required placeholder="Ex.: Chamado 1234 — verificar dados operacionais"></textarea>
                                <small class="form-text">Use entre 10 e 255 caracteres.</small>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-primary" type="submit"><i class="bi bi-box-arrow-in-right"></i> Entrar no painel operacional</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php admin_pagination($page, $result['total'], 20, 'empresas.php', ['q' => $search, 'status' => $status]); ?>
    <?php endif; ?>
</section>
<?php };
