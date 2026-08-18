<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Candidatos',
    'description' => 'Base única de candidatos cadastrados manualmente ou importados da planilha do programa.',
    'actions' => [],
    'modal' => ['title' => 'Candidato'],
];

$dbReady = pe_db_ready();
$rows = [];
$stats = ['total'=>0,'contemplados'=>0,'visitas'=>0,'deferidos'=>0,'indeferidos'=>0,'importados'=>0];
$filters = ['q' => trim((string)($_GET['q'] ?? '')), 'status' => trim((string)($_GET['status'] ?? ''))];
if ($dbReady) {
    $pdo = pe_db();
    $rows = pe_report_rows($pdo, $filters);
    $stats = pe_dashboard_stats($pdo);
}

ob_start();
?>
<section class="content-card pe-form-card">
    <?php if (!$dbReady): ?><?= pe_db_notice() ?><?php endif; ?>
    <div class="pe-form-header"><div><div class="card-kicker">Banco de candidatos</div><h2>Cadastros do programa</h2><p>Pesquisa integrada entre triagem, visita social e ficha cadastral.</p></div></div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="pe-kpi"><span>Total cadastrado</span><strong><?= (int)$stats['total'] ?></strong></div></div>
        <div class="col-6 col-xl-3"><div class="pe-kpi"><span>Contemplados</span><strong><?= (int)$stats['contemplados'] ?></strong></div></div>
        <div class="col-6 col-xl-3"><div class="pe-kpi"><span>Visitas realizadas</span><strong><?= (int)$stats['visitas'] ?></strong></div></div>
        <div class="col-6 col-xl-3"><div class="pe-kpi"><span>Importados</span><strong><?= (int)$stats['importados'] ?></strong></div></div>
    </div>
    <form method="get" class="row g-2 mb-3 pe-no-print"><div class="col-md-7"><input class="form-control" name="q" value="<?= pe_h($filters['q']) ?>" placeholder="Pesquisar nome, CPF, bairro ou setor"></div><div class="col-md-3"><select class="form-select" name="status"><option value="">Todos os status</option><?php foreach(['Em triagem','Em análise','Deferido','Indeferido','Importado','Contemplado'] as $v): ?><option<?= $filters['status']===$v?' selected':'' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Pesquisar</button></div></form>
    <div class="table-responsive"><table class="table align-middle pe-data-table"><thead><tr><th>Nome</th><th>CPF</th><th>Idade</th><th>Bairro</th><th>Telefone</th><th>Setor</th><th>Parecer</th><th>Status</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr><?php endif; foreach($rows as $row): ?><tr><td><strong><?= pe_h($row['nome']) ?></strong><small class="d-block text-muted">#<?= (int)$row['id'] ?> · <?= pe_h($row['origem']) ?></small></td><td><?= pe_h($row['cpf']) ?></td><td><?= pe_h(pe_age($row['data_nascimento'])) ?></td><td><?= pe_h($row['bairro']) ?></td><td><?= pe_h($row['telefone']) ?></td><td><?= pe_h($row['setor']) ?></td><td><?= pe_h($row['parecer'] ?: '—') ?></td><td><span class="badge text-bg-light"><?= pe_h($row['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<?php $pageCustomContent = (string)ob_get_clean();
