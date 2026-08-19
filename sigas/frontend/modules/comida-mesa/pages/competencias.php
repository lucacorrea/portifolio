<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/forms.php';

$pageDefinition = [
    'title' => 'Competências',
    'description' => 'Gestão dos ciclos mensais de distribuição, abertura, encerramento e execução das entregas.',
    'actions' => cm_can('comida_mesa.competencias_gerenciar') ? [
        ['label'=>'Nova competência','icon'=>'calendar-plus','primary'=>true,'href'=>'comida-mesa/competencias.php?modal=nova'],
    ] : [],
    'demo'=>false,'show_states'=>false,
];

$filters = [
    'ano' => trim((string)($_GET['ano'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
];
$rows = $moduleRepository->competences($filters);
$total = count($rows);
$abertas = count(array_filter($rows, static fn($r)=>(string)$r['status']==='aberta'));
$planejadas = count(array_filter($rows, static fn($r)=>(string)$r['status']==='planejada'));
$encerradas = count(array_filter($rows, static fn($r)=>(string)$r['status']==='encerrada'));
$entregas = array_sum(array_map(static fn($r)=>(int)$r['entregas'],$rows));
$years=[]; foreach($moduleRepository->competences([]) as $r){$years[(string)$r['ano']]=(string)$r['ano'];} rsort($years);

ob_start();
?>
<section class="content-card cm-list-card">
    <?php cm_list_header('Ciclos mensais','Competências do programa','Cada competência controla o período de distribuição e consolida suas entregas.', cm_can('comida_mesa.competencias_gerenciar')?'Nova competência':'', cm_can('comida_mesa.competencias_gerenciar')?'#competenceModal':'', 'calendar-plus'); ?>
    <?php cm_metrics([
        ['label'=>'Competências','value'=>$total,'hint'=>'Registros encontrados','tone'=>'neutral'],
        ['label'=>'Abertas','value'=>$abertas,'hint'=>'Recebem entregas','tone'=>'success'],
        ['label'=>'Planejadas','value'=>$planejadas,'hint'=>'Próximos ciclos','tone'=>'warning'],
        ['label'=>'Encerradas','value'=>$encerradas,'hint'=>'Histórico consolidado','tone'=>'muted'],
        ['label'=>'Entregas','value'=>$entregas,'hint'=>'Nos ciclos filtrados','tone'=>'info'],
    ]); ?>
    <form class="cm-filter-panel cm-filter-panel--compact" method="get" action="comida-mesa/competencias.php">
        <label><span>Ano</span><select class="form-select" name="ano"><option value="">Todos</option><?php foreach($years as $year): ?><option value="<?= cm_h($year) ?>"<?= cm_selected($filters['ano'],$year) ?>><?= cm_h($year) ?></option><?php endforeach; ?></select></label>
        <label><span>Situação</span><select class="form-select" name="status"><option value="">Todas</option><option value="planejada"<?= cm_selected($filters['status'],'planejada') ?>>Planejada</option><option value="aberta"<?= cm_selected($filters['status'],'aberta') ?>>Aberta</option><option value="encerrada"<?= cm_selected($filters['status'],'encerrada') ?>>Encerrada</option><option value="cancelada"<?= cm_selected($filters['status'],'cancelada') ?>>Cancelada</option></select></label>
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn btn-light" href="comida-mesa/competencias.php"><i class="bi bi-x-lg"></i> Limpar</a>
    </form>
    <div class="cm-table-shell"><div class="cm-table-toolbar"><div><h3>Ciclos de distribuição</h3><p><?= $total ?> competência(s) encontrada(s)</p></div><?php if(cm_can('comida_mesa.competencias_gerenciar')): ?><span><i class="bi bi-cursor"></i> Clique na linha para editar</span><?php endif; ?></div>
    <?php if($rows): ?><div class="table-responsive"><table class="cm-data-table"><thead><tr><th>Competência</th><th>Período</th><th>Situação</th><th>Entregas</th><th>Canceladas</th><th>Polos atendidos</th><th>Observação</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr<?= cm_can('comida_mesa.competencias_gerenciar')?' tabindex="0" data-cm-competence-row data-id="'.(int)$row['id'].'"':'' ?>><td><strong><?= cm_h(cm_month_label((int)$row['mes'],(int)$row['ano'])) ?></strong></td><td><?= cm_h(cm_date($row['inicio_entregas'])) ?> → <?= cm_h(cm_date($row['fim_entregas'])) ?></td><td><?= cm_status(cm_competence_label((string)$row['status'])) ?></td><td><strong><?= (int)$row['entregas'] ?></strong></td><td><?= (int)$row['canceladas'] ?></td><td><?= (int)$row['polos_com_entrega'] ?></td><td><?= cm_h($row['observacao'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><?php cm_empty('Nenhuma competência encontrada','Cadastre uma competência ou limpe os filtros.','calendar3'); ?><?php endif; ?></div>
</section>
<?php cm_competence_modal(); ?>
<?php if(($_GET['modal']??'')==='nova' && cm_can('comida_mesa.competencias_gerenciar')): ?><script>document.addEventListener('DOMContentLoaded',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('competenceModal')).show());</script><?php endif; ?>
<?php $pageCustomContent=(string)ob_get_clean(); ?>
