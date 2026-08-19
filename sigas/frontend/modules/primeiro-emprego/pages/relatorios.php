<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Relatórios',
    'description' => 'Relatório consolidado no mesmo padrão da planilha utilizada pelo Meu Primeiro Emprego.',
    'actions' => [['label' => 'Exportar CSV', 'icon' => 'download', 'href' => 'primeiro-emprego/relatorios.php?pe_export=csv']],
    'demo' => false,
    'show_states' => false,
    'modal' => ['title' => 'Relatório'],
];

$dbReady = pe_db_ready() && pe_schema_ready();
$pdo = $dbReady ? pe_db() : null;
$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'bairro' => trim((string)($_GET['bairro'] ?? '')),
    'setor' => trim((string)($_GET['setor'] ?? '')),
];
$rows = $dbReady ? pe_report_rows($pdo, $filters) : [];
$stats = $dbReady ? pe_dashboard_stats($pdo) : ['total'=>0,'contemplados'=>0,'visitas'=>0,'deferidos'=>0,'indeferidos'=>0,'importados'=>0];

if ($dbReady && ($_GET['pe_export'] ?? '') === 'csv') {
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="relatorio_primeiro_emprego_' . date('Y-m-d_H-i') . '.csv"');
    }
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['#','NOME','DATA NASC.','RESPONSAVEL','BAIRRO','ENDEREÇO','TELEFONE','CPF','IDADE','SETOR','PARECER','STATUS'], ';');
    $i = 0;
    foreach ($rows as $row) {
        $i++;
        fputcsv($out, [$i, $row['nome'], $row['data_nascimento'] ? date('d/m/Y', strtotime($row['data_nascimento'])) : '', $row['responsavel_familiar'], $row['bairro'], $row['endereco'], $row['telefone'], $row['cpf'], pe_age($row['data_nascimento']), $row['setor'], $row['parecer'], $row['status']], ';');
    }
    fclose($out);
    exit;
}

$bairros = [];
$setores = [];
if ($dbReady) {
    $bairros = $pdo->query('SELECT DISTINCT bairro FROM pe_candidatos WHERE bairro IS NOT NULL AND bairro <> "" ORDER BY bairro')->fetchAll(PDO::FETCH_COLUMN);
    $setores = $pdo->query('SELECT DISTINCT local_atuacao FROM pe_fichas_cadastrais WHERE local_atuacao IS NOT NULL AND local_atuacao <> "" ORDER BY local_atuacao')->fetchAll(PDO::FETCH_COLUMN);
}
$query = $_GET;
$query['pe_export'] = 'csv';
$exportUrl = 'primeiro-emprego/relatorios.php?' . http_build_query($query);

ob_start();
?>
<section class="content-card pe-form-card">
    <?php if (!$dbReady): ?><?= pe_db_notice() ?><?php endif; ?>
    <div class="pe-form-header"><div><div class="card-kicker">Relatório operacional</div><h2>Contemplados e candidatos</h2><p>A tabela segue as colunas da planilha original e acrescenta parecer e status para gestão.</p></div><div class="d-flex gap-2 pe-no-print"><a class="btn btn-outline-primary" href="<?= pe_h($exportUrl) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a><button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button></div></div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Total</span><strong><?= (int)$stats['total'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Contemplados</span><strong><?= (int)$stats['contemplados'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Visitas</span><strong><?= (int)$stats['visitas'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Deferidos</span><strong><?= (int)$stats['deferidos'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Indeferidos</span><strong><?= (int)$stats['indeferidos'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Importados</span><strong><?= (int)$stats['importados'] ?></strong></div></div>
    </div>
    <form method="get" class="row g-2 mb-4 pe-no-print">
        <div class="col-lg-4"><input class="form-control" name="q" value="<?= pe_h($filters['q']) ?>" placeholder="Nome, CPF, bairro ou setor"></div>
        <div class="col-lg-2"><select class="form-select" name="status"><option value="">Status</option><?php foreach(['Em triagem','Em análise','Deferido','Indeferido','Importado','Contemplado'] as $v): ?><option<?= $filters['status']===$v?' selected':'' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2"><select class="form-select" name="bairro"><option value="">Bairro</option><?php foreach($bairros as $v): ?><option<?= $filters['bairro']===$v?' selected':'' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2"><select class="form-select" name="setor"><option value="">Setor</option><?php foreach($setores as $v): ?><option<?= $filters['setor']===$v?' selected':'' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2"><button class="btn btn-primary w-100">Filtrar</button></div>
    </form>
    <div class="table-responsive"><table class="table table-sm align-middle pe-data-table pe-report-table"><thead><tr><th>#</th><th>NOME</th><th>DATA NASC.</th><th>RESPONSÁVEL</th><th>BAIRRO</th><th>ENDEREÇO</th><th>TELEFONE</th><th>CPF</th><th>IDADE</th><th>SETOR</th><th>PARECER</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="11" class="text-center text-muted py-4">Nenhum registro.</td></tr><?php endif; $i=0; foreach($rows as $row): $i++; ?><tr><td><?= $i ?></td><td><?= pe_h($row['nome']) ?></td><td><?= $row['data_nascimento'] ? pe_h(date('d/m/Y',strtotime($row['data_nascimento']))) : '' ?></td><td><?= pe_h($row['responsavel_familiar']) ?></td><td><?= pe_h($row['bairro']) ?></td><td><?= pe_h($row['endereco']) ?></td><td><?= pe_h($row['telefone']) ?></td><td><?= pe_h($row['cpf']) ?></td><td><?= pe_h(pe_age($row['data_nascimento'])) ?></td><td><?= pe_h($row['setor']) ?></td><td><?= pe_h($row['parecer']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<?php $pageCustomContent = (string)ob_get_clean();
