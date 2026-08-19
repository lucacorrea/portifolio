<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/forms.php';
cm_require('comida_mesa.historico_visualizar');
$pageDefinition=['title'=>'Histórico','description'=>'Auditoria operacional das alterações, entregas e movimentações realizadas no programa.','actions'=>[],'demo'=>false,'show_states'=>false];
$filters=['q'=>trim((string)($_GET['q']??'')),'acao'=>trim((string)($_GET['acao']??'')),'data_inicio'=>trim((string)($_GET['data_inicio']??'')),'data_fim'=>trim((string)($_GET['data_fim']??''))];
$rows=$moduleRepository->histories($filters,500); $actions=$moduleRepository->historyActions(); $today=count(array_filter($rows,static fn($r)=>substr((string)$r['criado_em'],0,10)===date('Y-m-d'))); $deliveries=count(array_filter($rows,static fn($r)=>str_contains((string)$r['acao'],'entrega'))); $changes=count(array_filter($rows,static fn($r)=>str_contains((string)$r['acao'],'edit')||str_contains((string)$r['acao'],'atualiz')));
ob_start();
?>
<section class="content-card cm-list-card">
<?php cm_list_header('Auditoria e rastreabilidade','Histórico do programa','Consulte quem fez cada movimentação e em qual família, sem alterar o registro histórico.'); ?>
<?php cm_metrics([['label'=>'Eventos encontrados','value'=>count($rows),'hint'=>'Resultado dos filtros','tone'=>'neutral'],['label'=>'Eventos hoje','value'=>$today,'hint'=>'Movimentações do dia','tone'=>'success'],['label'=>'Eventos de entrega','value'=>$deliveries,'hint'=>'Registros e cancelamentos','tone'=>'info'],['label'=>'Atualizações cadastrais','value'=>$changes,'hint'=>'Mudanças de cadastro','tone'=>'warning']]); ?>
<form class="cm-filter-panel cm-filter-panel--history" method="get" action="comida-mesa/historico.php"><label class="cm-filter-search"><span>Pesquisa</span><div class="cm-input-icon"><i class="bi bi-search"></i><input class="form-control" name="q" value="<?= cm_h($filters['q']) ?>" placeholder="Família, responsável, ação ou operador"></div></label><label><span>Ação</span><select class="form-select" name="acao"><option value="">Todas</option><?php foreach($actions as $action): ?><option value="<?= cm_h($action) ?>"<?= cm_selected($filters['acao'],$action) ?>><?= cm_h($action) ?></option><?php endforeach; ?></select></label><label><span>De</span><input class="form-control" type="date" name="data_inicio" value="<?= cm_h($filters['data_inicio']) ?>"></label><label><span>Até</span><input class="form-control" type="date" name="data_fim" value="<?= cm_h($filters['data_fim']) ?>"></label><button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn btn-light" href="comida-mesa/historico.php"><i class="bi bi-x-lg"></i> Limpar</a></form>
<div class="cm-table-shell"><div class="cm-table-toolbar"><div><h3>Movimentações</h3><p>Até 500 eventos por consulta</p></div></div><?php if($rows): ?><div class="table-responsive"><table class="cm-data-table"><thead><tr><th>Data/hora</th><th>Família</th><th>Responsável</th><th>Ação</th><th>Descrição</th><th>Operador</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr tabindex="0" data-cm-history-row data-registration-id="<?= (int)$row['inscricao_id'] ?>"><td><?= cm_h(cm_date($row['criado_em'],true)) ?></td><td><strong><?= cm_h($row['familia_codigo']) ?></strong></td><td><?= cm_h($row['responsavel_nome']) ?></td><td><?= cm_status((string)$row['acao']) ?></td><td><?= cm_h($row['descricao'] ?: '—') ?></td><td><?= cm_h($row['usuario_nome'] ?: 'Sistema') ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><?php cm_empty('Nenhum evento encontrado','Ajuste os filtros para consultar o histórico.','clock-history'); ?><?php endif; ?></div>
</section>
<?php cm_detail_modal(); ?>
<?php $pageCustomContent=(string)ob_get_clean(); ?>
